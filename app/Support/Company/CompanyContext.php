<?php

namespace App\Support\Company;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Modules\Organisation\Models\Company;

final class CompanyContext
{
    /** Company ids the logged-in user may see. All active companies when user->all_companies. [] when no user. */
    public static function permittedIds(): array
    {
        $user = Auth::user();

        if ($user === null) {
            return [];
        }

        if ($user->all_companies) {
            return Company::query()
                ->where('is_active', true)
                ->orderBy('id')
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        return $user->companies()
            ->where('companies.is_active', true)
            ->orderBy('companies.id')
            ->pluck('companies.id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** Currently selected company id (session key 'company_id'), or null = all permitted. */
    public static function id(): ?int
    {
        $companyId = session('company_id');

        return $companyId === null ? null : (int) $companyId;
    }

    /** Select a company, or null for all. Throws AuthorizationException if $companyId is not permitted. */
    public static function set(?int $companyId): void
    {
        if ($companyId !== null && ! in_array($companyId, self::permittedIds(), true)) {
            throw new AuthorizationException('You may not select that company.');
        }

        session(['company_id' => $companyId]);
    }

    /** When nothing is selected and the user may see exactly one company, select it. */
    public static function ensureSoleSelected(): void
    {
        if (self::id() !== null) {
            return;
        }

        $ids = self::permittedIds();
        if (count($ids) === 1) {
            self::set($ids[0]);
        }
    }
}
