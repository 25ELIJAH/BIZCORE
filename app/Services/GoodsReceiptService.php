<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Exceptions\BusinessRuleException;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Receiving goods is the ONLY thing that increases stock for a purchase order.
 * Creating or approving an order never changes inventory.
 */
class GoodsReceiptService
{
    public function __construct(
        private StockService $stock,
        private DocumentNumberService $numbers,
        private ActivityLogger $activityLogger,
    ) {}

    /**
     * Record a (possibly partial) delivery.
     *
     * @param  list<array{purchase_order_item_id: int, quantity: string|int|float}>  $lines  zero/blank quantities are ignored
     */
    public function receive(PurchaseOrder $order, array $lines, string|Carbon|null $receivedDate = null, ?string $notes = null): GoodsReceipt
    {
        $lines = array_values(array_filter($lines, fn ($l) => isset($l['quantity']) && is_numeric((string) $l['quantity']) && bccomp((string) $l['quantity'], '0', 3) !== 0));

        if ($lines === []) {
            throw new BusinessRuleException('Enter a received quantity for at least one item.');
        }

        return DB::transaction(function () use ($order, $lines, $receivedDate, $notes) {
            $locked = PurchaseOrder::query()->lockForUpdate()->findOrFail($order->getKey());

            if (! $locked->canReceive()) {
                throw new BusinessRuleException('Goods can only be received against approved orders that are not fully received.');
            }

            $date = Carbon::parse($receivedDate ?? today());
            if ($date->gt(today())) {
                throw new BusinessRuleException('Received date cannot be in the future.');
            }

            $receipt = (new GoodsReceipt)->forceFill([
                'purchase_order_id' => $locked->id,
                'user_id' => Auth::id(),
                'receipt_number' => $this->numbers->next('goods_receipt', 'GRN'),
                'received_date' => $date,
                'notes' => $notes,
            ]);
            $receipt->save();

            $seen = [];
            foreach ($lines as $line) {
                $itemId = (int) $line['purchase_order_item_id'];
                if (isset($seen[$itemId])) {
                    throw new BusinessRuleException('Each item can appear only once per receipt.');
                }
                $seen[$itemId] = true;

                // Locked and scoped to this order: another order's (or company's) item id cannot be used.
                $item = PurchaseOrderItem::query()->lockForUpdate()->where('purchase_order_id', $locked->id)->find($itemId);
                if (! $item) {
                    throw new BusinessRuleException('One of the received items does not belong to this purchase order.');
                }

                $quantity = bcadd((string) $line['quantity'], '0', 3);
                if (bccomp($quantity, '0', 3) < 0) {
                    throw new BusinessRuleException("Received quantity for {$item->product_name} cannot be negative.");
                }

                $outstanding = $item->quantityOutstanding();
                if (bccomp($quantity, $outstanding, 3) > 0) {
                    throw new BusinessRuleException("Cannot receive {$quantity} of {$item->product_name}: only {$outstanding} still outstanding.");
                }

                (new GoodsReceiptItem)->forceFill([
                    'goods_receipt_id' => $receipt->id,
                    'purchase_order_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'quantity' => $quantity,
                    'unit_cost' => $item->unit_cost,
                ])->save();

                $item->forceFill(['quantity_received' => bcadd((string) $item->quantity_received, $quantity, 3)])->save();

                $product = Product::withTrashed()->findOrFail($item->product_id);
                $this->stock->add($product, $quantity, StockMovementType::Purchase, $item->unit_cost, $receipt, "Received {$receipt->receipt_number} ({$locked->po_number})");
            }

            $fullyReceived = ! $locked->items()->whereColumn('quantity_received', '<', 'quantity_ordered')->exists();
            $locked->forceFill(['status' => $fullyReceived ? PurchaseOrder::RECEIVED : PurchaseOrder::PARTIAL])->save();

            $this->activityLogger->log('goods.received', $receipt, [
                'po_number' => $locked->po_number, 'receipt' => $receipt->receipt_number, 'lines' => count($lines), 'status' => $locked->status,
            ]);

            $order->setRawAttributes($locked->getAttributes(), true);

            return $receipt->load('items');
        });
    }
}
