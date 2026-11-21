<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'description'])]
class ProductCategory extends Model
{
    use BelongsToCompany, HasFactory;

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
