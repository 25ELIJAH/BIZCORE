<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinanceCategory extends Model
{
    use BelongsToCompany;

    public const INCOME = 'income';

    public const EXPENSE = 'expense';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['is_system' => 'boolean', 'is_active' => 'boolean'];
    }

    public function entries(): HasMany
    {
        return $this->hasMany(FinanceEntry::class);
    }
}
