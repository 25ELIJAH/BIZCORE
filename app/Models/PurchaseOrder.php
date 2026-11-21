<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Created and changed only through App\Services\PurchaseOrderService and friends. */
class PurchaseOrder extends Model
{
    use BelongsToCompany, HasFactory;

    public const DRAFT = 'draft';

    public const PENDING = 'pending_approval';

    public const APPROVED = 'approved';

    public const PARTIAL = 'partially_received';

    public const RECEIVED = 'received';

    public const CANCELLED = 'cancelled';

    /** Statuses from which the supplier can be owed money (approved or later, not cancelled). */
    public const PAYABLE_STATUSES = [self::APPROVED, self::PARTIAL, self::RECEIVED];

    public const STATUS_LABELS = [
        self::DRAFT => 'Draft', self::PENDING => 'Pending approval', self::APPROVED => 'Approved',
        self::PARTIAL => 'Partially received', self::RECEIVED => 'Received', self::CANCELLED => 'Cancelled',
    ];

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'order_date' => 'date', 'expected_date' => 'date',
            'submitted_at' => 'datetime', 'approved_at' => 'datetime', 'cancelled_at' => 'datetime',
            'subtotal' => 'decimal:2', 'tax_rate' => 'decimal:2', 'tax_amount' => 'decimal:2',
            'total' => 'decimal:2', 'amount_paid' => 'decimal:2',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class);
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function isPayable(): bool
    {
        return in_array($this->status, self::PAYABLE_STATUSES, true);
    }

    public function canReceive(): bool
    {
        return in_array($this->status, [self::APPROVED, self::PARTIAL], true);
    }

    /** Amount still owed to the supplier on this order. */
    public function balance(): string
    {
        return $this->isPayable() ? bcsub((string) $this->total, (string) $this->amount_paid, 2) : '0.00';
    }
}
