<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Created and changed only through App\Services\SaleService / PaymentService.
 * Money columns are never mass-assignable.
 */
class Sale extends Model
{
    use BelongsToCompany, HasFactory;

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const PAYMENT_UNPAID = 'unpaid';

    public const PAYMENT_PARTIAL = 'partial';

    public const PAYMENT_PAID = 'paid';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'sale_date' => 'date',
            'due_date' => 'date',
            'cancelled_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'discount_value' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'amount_paid' => 'decimal:2',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function balance(): string
    {
        return $this->isCancelled() ? '0.00' : bcsub((string) $this->total, (string) $this->amount_paid, 2);
    }

    public function isOverdue(): bool
    {
        return ! $this->isCancelled()
            && $this->payment_status !== self::PAYMENT_PAID
            && $this->due_date->lt(today());
    }

    /** Completed sales still carrying a balance past their due date. */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_COMPLETED)
            ->where('payment_status', '!=', self::PAYMENT_PAID)
            ->whereDate('due_date', '<', today());
    }
}
