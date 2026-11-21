<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An income or expense. Never deleted: voided with a reason. Derived entries are managed through their source payment. */
class FinanceEntry extends Model
{
    use BelongsToCompany;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'entry_date' => 'date', 'voided_at' => 'datetime'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(FinanceCategory::class, 'finance_category_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    public function isDerived(): bool
    {
        return $this->source_type !== null;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('voided_at');
    }
}
