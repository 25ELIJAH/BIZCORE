<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'contact_person', 'email', 'phone', 'address', 'tax_number', 'notes', 'is_active'])]
class Supplier extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    /** Money still owed to this supplier: approved-or-later orders (not cancelled) minus payments made. */
    public function outstandingBalance(): string
    {
        $row = $this->purchaseOrders()->whereIn('status', PurchaseOrder::PAYABLE_STATUSES)
            ->selectRaw('COALESCE(SUM(total - amount_paid), 0) as balance')->first();

        return bcadd((string) $row->balance, '0', 2);
    }
}
