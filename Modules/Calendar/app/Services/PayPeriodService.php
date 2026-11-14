<?php

namespace Modules\Calendar\Services;

use App\Models\User;
use Carbon\Carbon;
use Modules\Calendar\Enums\PeriodStatus;
use Modules\Calendar\Exceptions\InvalidPeriodAction;
use Modules\Calendar\Exceptions\PeriodNotFound;
use Modules\Calendar\Models\PayPeriod;
use Modules\Organisation\Models\Company;

final class PayPeriodService
{
    public function ensure(Company $c, int $year, int $month): PayPeriod
    {
        $existing = PayPeriod::withoutGlobalScopes()
            ->where('company_id', $c->id)
            ->where('year', $year)
            ->where('month', $month)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $start = Carbon::create($year, $month, 1, 0, 0, 0, config('app.timezone'))->startOfDay();

        return PayPeriod::query()->create([
            'company_id' => $c->id,
            'year' => $year,
            'month' => $month,
            'start_date' => $start->toDateString(),
            'end_date' => $start->copy()->endOfMonth()->toDateString(),
            'status' => PeriodStatus::OPEN,
            'closed_at' => null,
            'closed_by' => null,
            'close_version' => 0,
        ]);
    }

    /** Ensures periods for every active company from $from's month to $to's month. Returns rows created. */
    public function ensureRange(\DateTimeInterface|string $from, \DateTimeInterface|string $to): int
    {
        $cursor = Carbon::parse($from, config('app.timezone'))->startOfMonth();
        $end = Carbon::parse($to, config('app.timezone'))->startOfMonth();

        $companies = Company::query()->where('is_active', true)->orderBy('id')->get();
        $created = 0;

        while ($cursor->lte($end)) {
            foreach ($companies as $company) {
                $before = PayPeriod::withoutGlobalScopes()
                    ->where('company_id', $company->id)
                    ->where('year', $cursor->year)
                    ->where('month', $cursor->month)
                    ->exists();

                $this->ensure($company, (int) $cursor->year, (int) $cursor->month);

                if (! $before) {
                    $created++;
                }
            }

            $cursor->addMonth();
        }

        return $created;
    }

    public function forDate(int $companyId, \DateTimeInterface|string $date): PayPeriod
    {
        $day = Carbon::parse($date, config('app.timezone'))->toDateString();

        $period = PayPeriod::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereDate('start_date', '<=', $day)
            ->whereDate('end_date', '>=', $day)
            ->first();

        if ($period === null) {
            throw new PeriodNotFound('No pay period exists for that date.');
        }

        return $period;
    }

    public function isDateLocked(int $companyId, \DateTimeInterface|string $date): bool
    {
        try {
            $period = $this->forDate($companyId, $date);
        } catch (PeriodNotFound) {
            return false;
        }

        return $period->status === PeriodStatus::CLOSED;
    }

    public function previous(PayPeriod $p): ?PayPeriod
    {
        $date = Carbon::create((int) $p->year, (int) $p->month, 1)->subMonth();

        return PayPeriod::withoutGlobalScopes()
            ->where('company_id', $p->company_id)
            ->where('year', $date->year)
            ->where('month', $date->month)
            ->first();
    }

    public function next(PayPeriod $p): ?PayPeriod
    {
        $date = Carbon::create((int) $p->year, (int) $p->month, 1)->addMonth();

        return PayPeriod::withoutGlobalScopes()
            ->where('company_id', $p->company_id)
            ->where('year', $date->year)
            ->where('month', $date->month)
            ->first();
    }

    /** Low-level status change used by Task 23. Does not run the checklist. */
    public function markClosed(PayPeriod $p, ?User $by): PayPeriod
    {
        if ($p->status === PeriodStatus::CLOSED) {
            throw new InvalidPeriodAction('The period is already closed.');
        }

        $today = Carbon::now(config('app.timezone'))->startOfDay();
        if ($today->lte($p->end_date->copy()->startOfDay())) {
            throw new InvalidPeriodAction('The period has not ended.');
        }

        $previous = $this->previous($p);
        if ($previous !== null && $previous->status === PeriodStatus::OPEN) {
            $monthName = Carbon::create((int) $previous->year, (int) $previous->month, 1)->format('F');
            throw new InvalidPeriodAction("Close {$monthName} first.");
        }

        $p->update([
            'status' => PeriodStatus::CLOSED,
            'closed_at' => Carbon::now(config('app.timezone')),
            'closed_by' => $by?->id,
            'close_version' => ((int) $p->close_version) + 1,
        ]);

        return $p->fresh();
    }

    public function markOpen(PayPeriod $p): PayPeriod
    {
        if ($p->status === PeriodStatus::OPEN) {
            throw new InvalidPeriodAction('The period is already open.');
        }

        $next = $this->next($p);
        if ($next !== null && $next->status === PeriodStatus::CLOSED) {
            $monthName = Carbon::create((int) $next->year, (int) $next->month, 1)->format('F');
            throw new InvalidPeriodAction("Reopen {$monthName} first.");
        }

        $p->update([
            'status' => PeriodStatus::OPEN,
            'closed_at' => null,
            'closed_by' => null,
        ]);

        return $p->fresh();
    }
}
