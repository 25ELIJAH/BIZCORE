<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Exceptions\BusinessRuleException;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Support\Money;
use App\Support\Tenancy\CurrentCompany;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Completing a sale is ONE transaction: validate -> sale + items + invoice -> deduct stock -> optional first payment.
 * If any step fails (e.g. insufficient stock) nothing is saved.
 */
class SaleService
{
    public function __construct(
        private CurrentCompany $currentCompany,
        private StockService $stock,
        private PaymentService $payments,
        private DocumentNumberService $numbers,
        private ActivityLogger $activityLogger,
    ) {}

    /**
     * @param  array{
     *   customer_id?: int|null, sale_date?: string, due_date?: string|null, notes?: string|null,
     *   discount_type?: 'fixed'|'percent', discount_value?: string|int|float, tax_rate?: string|int|float|null,
     *   items: list<array{product_id: int, quantity: string|int|float, unit_price?: string|int|float|null}>,
     *   payment?: array{amount: string|int|float, method: string, reference?: string|null, idempotency_key?: string|null}|null
     * }  $data
     */
    public function create(array $data): Sale
    {
        $lines = $data['items'] ?? [];
        if ($lines === []) {
            throw new BusinessRuleException('A sale needs at least one item.');
        }

        return DB::transaction(function () use ($data, $lines) {
            $company = $this->currentCompany->get();
            $customer = ! empty($data['customer_id']) ? Customer::findOrFail($data['customer_id']) : null;
            if ($customer && ! $customer->is_active) {
                throw new BusinessRuleException('This customer is inactive.');
            }

            [$items, $subtotal] = $this->priceLines($lines);
            [$discountType, $discountValue, $discountAmount] = $this->discount($data, $subtotal);

            $taxable = Money::sub($subtotal, $discountAmount);
            $taxRate = (string) ($data['tax_rate'] ?? $company->setting('tax_rate', 0));
            if (! is_numeric($taxRate) || bccomp($taxRate, '0', 2) < 0 || bccomp($taxRate, '100', 2) > 0) {
                throw new BusinessRuleException('Tax rate must be between 0 and 100.');
            }
            $taxAmount = Money::mul($taxable, bcdiv($taxRate, '100', 6));
            $total = Money::add($taxable, $taxAmount);

            $saleDate = Carbon::parse($data['sale_date'] ?? today());
            $dueDate = ! empty($data['due_date']) ? Carbon::parse($data['due_date']) : $saleDate->copy()->addDays($company->paymentTermsDays());
            if ($dueDate->lt($saleDate)) {
                throw new BusinessRuleException('Due date cannot be before the sale date.');
            }

            $sale = (new Sale)->forceFill([
                'customer_id' => $customer?->id,
                'user_id' => Auth::id(),
                'sale_date' => $saleDate,
                'due_date' => $dueDate,
                'status' => Sale::STATUS_COMPLETED,
                'payment_status' => Sale::PAYMENT_UNPAID,
                'subtotal' => $subtotal,
                'discount_type' => $discountType,
                'discount_value' => $discountValue,
                'discount_amount' => $discountAmount,
                'tax_rate' => $taxRate,
                'tax_amount' => $taxAmount,
                'total' => $total,
                'amount_paid' => '0.00',
                'notes' => $data['notes'] ?? null,
            ]);
            $sale->save();

            $invoice = (new Invoice)->forceFill([
                'sale_id' => $sale->id,
                'invoice_number' => $this->numbers->next('invoice', 'INV'),
                'issue_date' => $saleDate,
                'due_date' => $dueDate,
            ]);
            $invoice->save();

            foreach ($items as $line) {
                /** @var Product $product */
                $product = $line['product'];
                (new SaleItem)->forceFill([
                    'sale_id' => $sale->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'sku' => $product->sku,
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'unit_cost' => $product->average_cost,
                    'line_total' => $line['line_total'],
                ])->save();

                // Throws InsufficientStockException (rolling everything back) when stock is short.
                $this->stock->deduct($product, $line['quantity'], StockMovementType::Sale, $sale, "Invoice {$invoice->invoice_number}");
            }

            $this->activityLogger->log('sale.created', $sale, ['invoice' => $invoice->invoice_number, 'total' => $total, 'items' => count($items)]);

            if (! empty($data['payment'])) {
                $p = $data['payment'];
                $this->payments->record($sale, $p['amount'], $p['method'], $saleDate, $p['reference'] ?? null, $p['idempotency_key'] ?? null);
            }

            return $sale->refresh()->load(['items', 'invoice', 'customer']);
        }, 5);
    }

    /**
     * Cancel a sale: stock is restored by a reversing movement; money already received must be voided first.
     */
    public function cancel(Sale $sale, string $reason): Sale
    {
        if (trim($reason) === '') {
            throw new BusinessRuleException('A reason is required to cancel a sale.');
        }

        return DB::transaction(function () use ($sale, $reason) {
            $locked = Sale::query()->lockForUpdate()->with('items', 'invoice')->findOrFail($sale->getKey());

            if ($locked->isCancelled()) {
                throw new BusinessRuleException('This sale is already cancelled.');
            }

            if ($locked->payments()->active()->exists()) {
                throw new BusinessRuleException('This sale has payments. Void the payments before cancelling the sale.');
            }

            foreach ($locked->items as $item) {
                $product = Product::withTrashed()->findOrFail($item->product_id);
                $this->stock->add($product, $item->quantity, StockMovementType::SaleReversal, $item->unit_cost, $locked, "Cancelled: {$reason}");
            }

            $locked->forceFill(['status' => Sale::STATUS_CANCELLED, 'cancelled_at' => now(), 'cancel_reason' => $reason])->save();
            $this->activityLogger->log('sale.cancelled', $locked, ['invoice' => $locked->invoice?->invoice_number, 'reason' => $reason]);

            return $locked;
        });
    }

    /** Limited correction after the fact: due date and notes only. Items and amounts are immutable. */
    public function updateDetails(Sale $sale, ?string $dueDate, ?string $notes): Sale
    {
        $sale->loadMissing('invoice');
        $due = $dueDate ? Carbon::parse($dueDate) : $sale->due_date;

        if ($due->lt($sale->sale_date)) {
            throw new BusinessRuleException('Due date cannot be before the sale date.');
        }

        DB::transaction(function () use ($sale, $due, $notes) {
            $sale->forceFill(['due_date' => $due, 'notes' => $notes])->save();
            $sale->invoice?->forceFill(['due_date' => $due])->save();
            $this->activityLogger->log('sale.updated', $sale, ['due_date' => $due->toDateString()]);
        });

        return $sale;
    }

    /** @return array{0: list<array<string, mixed>>, 1: string} lines and subtotal */
    private function priceLines(array $lines): array
    {
        $priced = [];
        $subtotal = '0.00';

        foreach ($lines as $line) {
            $product = Product::find($line['product_id'] ?? null);
            if (! $product || ! $product->is_active) {
                throw new BusinessRuleException('One of the products is unavailable.');
            }

            $quantity = (string) ($line['quantity'] ?? '');
            if (! is_numeric($quantity) || bccomp($quantity, '0', 3) <= 0) {
                throw new BusinessRuleException("Quantity for {$product->name} must be greater than zero.");
            }
            $quantity = bcadd($quantity, '0', 3);

            $price = (string) ($line['unit_price'] ?? $product->selling_price);
            if (! is_numeric($price) || bccomp($price, '0', 2) < 0) {
                throw new BusinessRuleException("Unit price for {$product->name} is invalid.");
            }
            $price = Money::round($price);

            $lineTotal = Money::mul($quantity, $price);
            $subtotal = Money::add($subtotal, $lineTotal);
            $priced[] = ['product' => $product, 'quantity' => $quantity, 'unit_price' => $price, 'line_total' => $lineTotal];
        }

        return [$priced, $subtotal];
    }

    /** @return array{0: string, 1: string, 2: string} type, entered value, amount taken off */
    private function discount(array $data, string $subtotal): array
    {
        $type = $data['discount_type'] ?? 'fixed';
        $value = (string) ($data['discount_value'] ?? 0);

        if (! in_array($type, ['fixed', 'percent'], true) || ! is_numeric($value) || bccomp($value, '0', 2) < 0) {
            throw new BusinessRuleException('Discount is invalid.');
        }

        $value = Money::round($value);
        if ($type === 'percent') {
            if (Money::compare($value, '100.00') > 0) {
                throw new BusinessRuleException('A percentage discount cannot exceed 100%.');
            }
            $amount = Money::mul($subtotal, bcdiv($value, '100', 6));
        } else {
            $amount = $value;
        }

        if (Money::compare($amount, $subtotal) > 0) {
            throw new BusinessRuleException('Discount cannot exceed the sale subtotal.');
        }

        return [$type, $value, $amount];
    }
}
