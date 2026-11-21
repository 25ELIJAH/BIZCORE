<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Exceptions\InsufficientStockException;
use App\Models\Product;
use App\Models\StockMovement;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The ONLY place product quantities change. Every change:
 *  - runs in a transaction with the product row locked (no lost updates under concurrency),
 *  - writes an append-only stock movement (previous / new quantity, user, reference),
 *  - maintains the weighted-average cost used for inventory valuation.
 *
 * Quantities are handled as bcmath strings (scale 3) to avoid float drift.
 */
class StockService
{
    public function __construct(
        private CurrentCompany $currentCompany,
        private ActivityLogger $activityLogger,
    ) {}

    /** Increase stock. $unitCost (when given) updates the weighted-average cost. */
    public function add(Product $product, string|int|float $quantity, StockMovementType $type = StockMovementType::Addition, string|int|float|null $unitCost = null, ?Model $reference = null, ?string $reason = null): StockMovement
    {
        if (! $type->isInbound()) {
            throw new InvalidArgumentException("{$type->value} is not an inbound movement type.");
        }

        return $this->record($product, $type, $quantity, $unitCost, $reference, $reason);
    }

    /** Decrease stock; refuses to go below zero unless the company allows negative stock. */
    public function deduct(Product $product, string|int|float $quantity, StockMovementType $type = StockMovementType::Deduction, ?Model $reference = null, ?string $reason = null): StockMovement
    {
        if ($type->isInbound()) {
            throw new InvalidArgumentException("{$type->value} is not an outbound movement type.");
        }

        return $this->record($product, $type, $quantity, null, $reference, $reason);
    }

    /** Stock-take correction: set the on-hand quantity to the counted figure. A reason is mandatory. */
    public function adjustTo(Product $product, string|int|float $countedQuantity, string $reason): ?StockMovement
    {
        if (trim($reason) === '') {
            throw new InvalidArgumentException('A reason is required for stock adjustments.');
        }

        if (bccomp($this->normalize($countedQuantity), '0', 3) < 0) {
            throw new InvalidArgumentException('Counted quantity cannot be negative.');
        }

        return DB::transaction(function () use ($product, $countedQuantity, $reason) {
            $locked = $this->lock($product);
            $difference = bcsub($this->normalize($countedQuantity), (string) $locked->quantity, 3);
            $comparison = bccomp($difference, '0', 3);

            if ($comparison === 0) {
                return null; // nothing to correct
            }

            $type = $comparison > 0 ? StockMovementType::AdjustmentIn : StockMovementType::AdjustmentOut;

            return $this->record($locked, $type, ltrim($difference, '-'), null, null, $reason);
        });
    }

    private function record(Product $product, StockMovementType $type, string|int|float $quantity, string|int|float|null $unitCost, ?Model $reference, ?string $reason): StockMovement
    {
        $quantity = $this->normalize($quantity);

        if (bccomp($quantity, '0', 3) <= 0) {
            throw new InvalidArgumentException('Quantity must be greater than zero.');
        }

        if (in_array($type, [StockMovementType::AdjustmentIn, StockMovementType::AdjustmentOut], true) && trim((string) $reason) === '') {
            throw new InvalidArgumentException('A reason is required for stock adjustments.');
        }

        return DB::transaction(function () use ($product, $type, $quantity, $unitCost, $reference, $reason) {
            $locked = $this->lock($product);
            $previous = (string) $locked->quantity;
            $signed = $type->isInbound() ? $quantity : '-'.$quantity;
            $new = bcadd($previous, $signed, 3);

            if (bccomp($new, '0', 3) < 0 && ! $this->negativeStockAllowed()) {
                throw new InsufficientStockException($locked, $quantity, $previous);
            }

            $attributes = ['quantity' => $new];

            if ($type->isInbound() && $unitCost !== null && bccomp($new, '0', 3) > 0) {
                $attributes['average_cost'] = $this->weightedAverageCost($locked, $quantity, (string) $unitCost);
            }

            $locked->forceFill($attributes)->save();

            $movement = StockMovement::create([
                'product_id' => $locked->id,
                'user_id' => Auth::id(),
                'type' => $type,
                'quantity' => $signed,
                'previous_quantity' => $previous,
                'new_quantity' => $new,
                'unit_cost' => $unitCost,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'reason' => $reason,
            ]);

            if (in_array($type, [StockMovementType::AdjustmentIn, StockMovementType::AdjustmentOut], true)) {
                $this->activityLogger->log('stock.adjusted', $locked, [
                    'old' => ['quantity' => $previous], 'new' => ['quantity' => $new], 'reason' => $reason,
                ]);
            }

            $product->setRawAttributes($locked->getAttributes(), true);

            return $movement;
        }, 5);
    }

    /** Re-read the product with a row lock. The company scope makes another company's product unreachable. */
    private function lock(Product $product): Product
    {
        // withTrashed: archived products still have stock that sales reversals / receipts must be able to move.
        return Product::withTrashed()->lockForUpdate()->findOrFail($product->getKey());
    }

    private function weightedAverageCost(Product $locked, string $incomingQty, string $incomingCost): string
    {
        $onHand = (string) $locked->quantity;

        if (bccomp($onHand, '0', 3) <= 0) {
            return bcadd($incomingCost, '0', 4);
        }

        $totalValue = bcadd(bcmul($onHand, (string) $locked->average_cost, 6), bcmul($incomingQty, $incomingCost, 6), 6);
        $totalQty = bcadd($onHand, $incomingQty, 3);

        return bcdiv($totalValue, $totalQty, 4);
    }

    private function negativeStockAllowed(): bool
    {
        return (bool) $this->currentCompany->get()?->setting('allow_negative_stock', false);
    }

    private function normalize(string|int|float $number): string
    {
        $string = is_float($number) ? number_format($number, 3, '.', '') : (string) $number;

        if (! is_numeric($string)) {
            throw new InvalidArgumentException("Not a valid quantity: {$string}");
        }

        return bcadd($string, '0', 3);
    }
}
