<?php

namespace App\Enums;

enum StockMovementType: string
{
    case Opening = 'opening';              // initial stock when a product is created
    case Purchase = 'purchase';            // goods received from a supplier
    case Sale = 'sale';                    // deducted by a completed sale
    case SaleReversal = 'sale_reversal';   // restored by a cancelled sale
    case AdjustmentIn = 'adjustment_in';   // manual correction, upwards
    case AdjustmentOut = 'adjustment_out'; // manual correction, downwards (loss, damage, count)
    case Addition = 'addition';            // manual stock addition
    case Deduction = 'deduction';          // manual stock deduction

    public function label(): string
    {
        return match ($this) {
            self::Opening => 'Opening stock',
            self::Purchase => 'Purchase received',
            self::Sale => 'Sale',
            self::SaleReversal => 'Sale reversed',
            self::AdjustmentIn => 'Adjustment (+)',
            self::AdjustmentOut => 'Adjustment (−)',
            self::Addition => 'Stock added',
            self::Deduction => 'Stock deducted',
        };
    }

    /** Types that increase stock. */
    public function isInbound(): bool
    {
        return in_array($this, [self::Opening, self::Purchase, self::SaleReversal, self::AdjustmentIn, self::Addition], true);
    }
}
