<?php

namespace Modules\Calendar\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Calendar\Enums\PeriodStatus;
use Modules\Calendar\Exceptions\InvalidExpectedDays;
use Modules\Calendar\Models\PayPeriod;
use Modules\Calendar\Models\PeriodExpectedDays;
use Modules\Organisation\Models\EmployeeCategory;

final class ExpectedDaysService
{
    public function set(PayPeriod $p, EmployeeCategory $category, float $days, ?User $by = null): PeriodExpectedDays
    {
        if ($p->status === PeriodStatus::CLOSED) {
            throw new InvalidExpectedDays('The period is closed.');
        }

        if (! $category->is_active) {
            throw new InvalidExpectedDays('Expected days can only be set for active categories.');
        }

        $daysInMonth = (int) Carbon::create((int) $p->year, (int) $p->month, 1)->daysInMonth;
        if ($days < 0 || $days > $daysInMonth || fmod($days * 2, 1.0) !== 0.0) {
            throw new InvalidExpectedDays('Expected days must be between 0 and the days in the month, in steps of 0.5.');
        }

        $row = PeriodExpectedDays::query()
            ->where('pay_period_id', $p->id)
            ->where('employee_category_id', $category->id)
            ->first();

        if ($row === null) {
            return PeriodExpectedDays::query()->create([
                'pay_period_id' => $p->id,
                'employee_category_id' => $category->id,
                'days' => $days,
                'set_by' => $by?->id,
            ]);
        }

        $row->update([
            'days' => $days,
            'set_by' => $by?->id,
        ]);

        return $row->fresh();
    }

    public function for(PayPeriod $p, int $categoryId): ?float
    {
        $row = PeriodExpectedDays::query()
            ->where('pay_period_id', $p->id)
            ->where('employee_category_id', $categoryId)
            ->first();

        return $row === null ? null : (float) $row->days;
    }

    /** Active categories with no value for the period. @return Collection<int, EmployeeCategory> */
    public function missing(PayPeriod $p): Collection
    {
        $setIds = PeriodExpectedDays::query()
            ->where('pay_period_id', $p->id)
            ->pluck('employee_category_id')
            ->all();

        return EmployeeCategory::query()
            ->where('is_active', true)
            ->whereNotIn('id', $setIds)
            ->orderBy('level')
            ->orderBy('name')
            ->get();
    }

    /** Copies every category's value from the previous period where the target has none. Returns rows added. */
    public function copyFromPrevious(PayPeriod $p): int
    {
        $previous = app(PayPeriodService::class)->previous($p);
        if ($previous === null) {
            return 0;
        }

        return (int) DB::transaction(function () use ($p, $previous) {
            $source = PeriodExpectedDays::query()
                ->where('pay_period_id', $previous->id)
                ->get()
                ->keyBy('employee_category_id');

            $existing = PeriodExpectedDays::query()
                ->where('pay_period_id', $p->id)
                ->pluck('employee_category_id')
                ->all();

            $added = 0;

            foreach ($source as $categoryId => $row) {
                if (in_array($categoryId, $existing, true)) {
                    continue;
                }

                $category = EmployeeCategory::query()->find($categoryId);
                if ($category === null || ! $category->is_active) {
                    continue;
                }

                PeriodExpectedDays::query()->create([
                    'pay_period_id' => $p->id,
                    'employee_category_id' => $categoryId,
                    'days' => $row->days,
                    'set_by' => null,
                ]);
                $added++;
            }

            return $added;
        });
    }
}
