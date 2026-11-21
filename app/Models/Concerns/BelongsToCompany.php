<?php

namespace App\Models\Concerns;

use App\Models\Company;
use App\Models\Scopes\CompanyScope;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Apply to every model whose table has a company_id column.
 * company_id is always taken from the current company context, never from input.
 */
trait BelongsToCompany
{
    public static function bootBelongsToCompany(): void
    {
        static::addGlobalScope(new CompanyScope);

        static::creating(function ($model) {
            $currentId = app(CurrentCompany::class)->id();

            if ($currentId === null) {
                throw new LogicException(static::class.' cannot be created without a current company.');
            }

            if ($model->company_id !== null && (int) $model->company_id !== $currentId) {
                throw new LogicException('Attempt to create a record for a company other than the current one.');
            }

            $model->company_id = $currentId;
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
