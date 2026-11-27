<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One M-Pesa STK push. Deliberately NOT tied to the current company: Safaricom calls back with no login,
 * so the callback finds the row by its secret uuid and sets the company context from the row itself.
 * Pages that list these always filter by company explicitly.
 */
class MpesaTransaction extends Model
{
    public const PENDING = 'pending';

    public const SUCCESS = 'success';

    public const FAILED = 'failed';

    /** Money arrived but could not be put against the sale automatically (for example the sale was cancelled meanwhile). */
    public const NEEDS_REVIEW = 'needs_review';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'callback_payload' => 'array', 'completed_at' => 'datetime'];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class)->withoutGlobalScopes();
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }
}
