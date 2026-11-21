<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'contact_person', 'email', 'phone', 'address', 'tax_number', 'notes', 'is_active'])]
class Customer extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    /**
     * Money the customer still owes: total of completed sales minus payments received.
     * Derived from the ledger rows, so it can never drift out of sync.
     */
    public function outstandingBalance(): string
    {
        $row = $this->sales()->where('status', Sale::STATUS_COMPLETED)->selectRaw('COALESCE(SUM(total - amount_paid), 0) as balance')->first();

        return bcadd((string) $row->balance, '0', 2);
    }
}
