<?php

namespace App\Support\Company;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/** Global scope: where company_id in permittedIds(); and = id() when one is selected.
 *  Does nothing when running in console or when no user is authenticated. */
final class CompanyScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        // Artisan console (except PHPUnit) never applies company filtering.
        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            return;
        }

        if (! Auth::check()) {
            return;
        }

        $selectedId = CompanyContext::id();

        if ($selectedId !== null) {
            $builder->where($model->getTable().'.company_id', $selectedId);

            return;
        }

        $builder->whereIn($model->getTable().'.company_id', CompanyContext::permittedIds());
    }
}
