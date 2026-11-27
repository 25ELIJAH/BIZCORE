<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * `quantity` and `average_cost` are NOT mass-assignable: they change only through
 * App\Services\StockService, which writes the matching stock movement.
 */
#[Fillable(['product_category_id', 'sku', 'name', 'description', 'unit', 'purchase_price', 'selling_price', 'tax_type', 'min_stock', 'is_active'])]
class Product extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    /** KRA VAT categories: standard rate, zero-rated (0% but still reported) and exempt (outside VAT). */
    public const TAX_TYPES = ['standard' => 'Standard rate', 'zero_rated' => 'Zero-rated (0%)', 'exempt' => 'Exempt'];

    protected function casts(): array
    {
        return [
            'purchase_price' => 'decimal:2',
            'selling_price' => 'decimal:2',
            'average_cost' => 'decimal:4',
            'quantity' => 'decimal:3',
            'min_stock' => 'decimal:3',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /** At or below the configured minimum (only meaningful when a minimum is set). */
    public function scopeLowStock(Builder $query): Builder
    {
        return $query->where('min_stock', '>', 0)->whereColumn('quantity', '<=', 'min_stock');
    }

    public function isLowStock(): bool
    {
        return bccomp((string) $this->min_stock, '0', 3) > 0 && bccomp((string) $this->quantity, (string) $this->min_stock, 3) <= 0;
    }

    /** Inventory valuation of this product: on-hand quantity × weighted-average cost. */
    public function stockValue(): string
    {
        return bcmul((string) $this->quantity, (string) $this->average_cost, 2);
    }
}
