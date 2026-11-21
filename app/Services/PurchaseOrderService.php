<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Support\Money;
use App\Support\Tenancy\CurrentCompany;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Purchase order life cycle:
 *   draft -> pending_approval -> approved -> partially_received -> received
 *   (rejected orders return to draft; draft/pending/approved orders with no receipts or payments can be cancelled)
 * Stock is NOT touched here. Only GoodsReceiptService changes stock.
 */
class PurchaseOrderService
{
    public function __construct(
        private CurrentCompany $currentCompany,
        private DocumentNumberService $numbers,
        private ActivityLogger $activityLogger,
    ) {}

    /**
     * Create a draft (or replace the contents of an existing draft).
     *
     * @param  array{supplier_id: int, order_date?: string, expected_date?: string|null, tax_rate?: string|int|float|null, notes?: string|null,
     *   items: list<array{product_id: int, quantity: string|int|float, unit_cost?: string|int|float|null}>}  $data
     */
    public function saveDraft(array $data, ?PurchaseOrder $order = null): PurchaseOrder
    {
        if (($data['items'] ?? []) === []) {
            throw new BusinessRuleException('A purchase order needs at least one item.');
        }

        return DB::transaction(function () use ($data, $order) {
            if ($order) {
                $order = PurchaseOrder::query()->lockForUpdate()->findOrFail($order->getKey());
                if ($order->status !== PurchaseOrder::DRAFT) {
                    throw new BusinessRuleException('Only draft purchase orders can be edited.');
                }
            }

            $supplier = Supplier::findOrFail($data['supplier_id']);
            if (! $supplier->is_active) {
                throw new BusinessRuleException('This supplier is inactive.');
            }

            [$lines, $subtotal] = $this->priceLines($data['items']);
            $taxRate = (string) ($data['tax_rate'] ?? 0);
            if (! is_numeric($taxRate) || bccomp($taxRate, '0', 2) < 0 || bccomp($taxRate, '100', 2) > 0) {
                throw new BusinessRuleException('Tax rate must be between 0 and 100.');
            }
            $tax = Money::mul($subtotal, bcdiv($taxRate, '100', 6));

            $orderDate = Carbon::parse($data['order_date'] ?? today());
            $expected = ! empty($data['expected_date']) ? Carbon::parse($data['expected_date']) : null;
            if ($expected && $expected->lt($orderDate)) {
                throw new BusinessRuleException('Expected date cannot be before the order date.');
            }

            $order ??= (new PurchaseOrder)->forceFill([
                'po_number' => $this->numbers->next('purchase_order', 'PO'),
                'status' => PurchaseOrder::DRAFT,
                'user_id' => Auth::id(),
            ]);
            $isNew = ! $order->exists;

            $order->forceFill([
                'supplier_id' => $supplier->id,
                'order_date' => $orderDate,
                'expected_date' => $expected,
                'subtotal' => $subtotal,
                'tax_rate' => Money::round($taxRate),
                'tax_amount' => $tax,
                'total' => Money::add($subtotal, $tax),
                'notes' => $data['notes'] ?? null,
                'rejection_reason' => null,
            ])->save();

            $order->items()->delete();
            foreach ($lines as $line) {
                (new PurchaseOrderItem)->forceFill([
                    'purchase_order_id' => $order->id,
                    'product_id' => $line['product']->id,
                    'product_name' => $line['product']->name,
                    'sku' => $line['product']->sku,
                    'quantity_ordered' => $line['quantity'],
                    'quantity_received' => '0.000',
                    'unit_cost' => $line['unit_cost'],
                    'line_total' => $line['line_total'],
                ])->save();
            }

            $this->activityLogger->log($isNew ? 'purchase_order.created' : 'purchase_order.updated', $order, ['po_number' => $order->po_number, 'total' => (string) $order->total]);

            return $order->load('items');
        });
    }

    public function submit(PurchaseOrder $order): PurchaseOrder
    {
        return $this->transition($order, [PurchaseOrder::DRAFT], function (PurchaseOrder $locked) {
            if (! $locked->items()->exists()) {
                throw new BusinessRuleException('A purchase order needs at least one item before it can be submitted.');
            }
            $locked->forceFill(['status' => PurchaseOrder::PENDING, 'submitted_at' => now()])->save();
        }, 'purchase_order.submitted', 'Only draft purchase orders can be submitted.');
    }

    public function approve(PurchaseOrder $order): PurchaseOrder
    {
        return $this->transition($order, [PurchaseOrder::PENDING], function (PurchaseOrder $locked) {
            $locked->forceFill(['status' => PurchaseOrder::APPROVED, 'approved_by' => Auth::id(), 'approved_at' => now()])->save();
        }, 'purchase_order.approved', 'Only orders pending approval can be approved.');
    }

    /** Send back to the buyer for changes. */
    public function reject(PurchaseOrder $order, string $reason): PurchaseOrder
    {
        $this->requireReason($reason, 'A reason is required to reject a purchase order.');

        return $this->transition($order, [PurchaseOrder::PENDING], function (PurchaseOrder $locked) use ($reason) {
            $locked->forceFill(['status' => PurchaseOrder::DRAFT, 'rejection_reason' => $reason, 'submitted_at' => null])->save();
        }, 'purchase_order.rejected', 'Only orders pending approval can be rejected.', ['reason' => $reason]);
    }

    public function cancel(PurchaseOrder $order, string $reason): PurchaseOrder
    {
        $this->requireReason($reason, 'A reason is required to cancel a purchase order.');

        return $this->transition($order, [PurchaseOrder::DRAFT, PurchaseOrder::PENDING, PurchaseOrder::APPROVED], function (PurchaseOrder $locked) use ($reason) {
            if ($locked->receipts()->exists()) {
                throw new BusinessRuleException('Goods have already been received against this order, so it cannot be cancelled.');
            }
            if ($locked->payments()->active()->exists()) {
                throw new BusinessRuleException('This order has supplier payments. Void them before cancelling.');
            }
            $locked->forceFill(['status' => PurchaseOrder::CANCELLED, 'cancelled_at' => now(), 'cancel_reason' => $reason])->save();
        }, 'purchase_order.cancelled', 'This purchase order can no longer be cancelled.', ['reason' => $reason]);
    }

    /** @param list<string> $allowedFrom */
    private function transition(PurchaseOrder $order, array $allowedFrom, callable $apply, string $action, string $errorMessage, array $props = []): PurchaseOrder
    {
        return DB::transaction(function () use ($order, $allowedFrom, $apply, $action, $errorMessage, $props) {
            $locked = PurchaseOrder::query()->lockForUpdate()->findOrFail($order->getKey());

            if (! in_array($locked->status, $allowedFrom, true)) {
                throw new BusinessRuleException($errorMessage);
            }

            $apply($locked);
            $this->activityLogger->log($action, $locked, ['po_number' => $locked->po_number, 'status' => $locked->status] + $props);
            $order->setRawAttributes($locked->getAttributes(), true);

            return $order;
        });
    }

    private function requireReason(string $reason, string $message): void
    {
        if (trim($reason) === '') {
            throw new BusinessRuleException($message);
        }
    }

    /** @return array{0: list<array<string, mixed>>, 1: string} */
    private function priceLines(array $items): array
    {
        $lines = [];
        $subtotal = '0.00';

        foreach ($items as $item) {
            $product = Product::find($item['product_id'] ?? null);
            if (! $product || ! $product->is_active) {
                throw new BusinessRuleException('One of the products is unavailable.');
            }

            $quantity = (string) ($item['quantity'] ?? '');
            if (! is_numeric($quantity) || bccomp($quantity, '0', 3) <= 0) {
                throw new BusinessRuleException("Quantity for {$product->name} must be greater than zero.");
            }

            $cost = (string) ($item['unit_cost'] ?? $product->purchase_price);
            if (! is_numeric($cost) || bccomp($cost, '0', 2) < 0) {
                throw new BusinessRuleException("Unit cost for {$product->name} is invalid.");
            }

            $quantity = bcadd($quantity, '0', 3);
            $cost = Money::round($cost);
            $lineTotal = Money::mul($quantity, $cost);
            $subtotal = Money::add($subtotal, $lineTotal);
            $lines[] = ['product' => $product, 'quantity' => $quantity, 'unit_cost' => $cost, 'line_total' => $lineTotal];
        }

        return [$lines, $subtotal];
    }
}
