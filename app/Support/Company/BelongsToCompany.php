<?php

namespace App\Support\Company;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Organisation\Models\Company;

/** Trait for models with a company_id column. Adds CompanyScope and company(): BelongsTo. */
trait BelongsToCompany
{
    public static function bootBelongsToCompany(): void
    {
        static::addGlobalScope(new CompanyScope);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
