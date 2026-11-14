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
use Modules\Organisation\Models\Grade;

final class ExpectedDaysService
{
    public function set(PayPeriod $p, Grade $g, float $days, ?User $by = null): PeriodExpectedDays
    {
        if ($p->status === PeriodStatus::CLOSED) {
            throw new InvalidExpectedDays('The period is closed.');
        }

        if (! $g->is_active) {
            throw new InvalidExpectedDays('Expected days can only be set for active grades.');
        }

        $daysInMonth = (int) Carbon::create((int) $p->year, (int) $p->month, 1)->daysInMonth;
        if ($days < 0 || $days > $daysInMonth || fmod($days * 2, 1.0) !== 0.0) {
            throw new InvalidExpectedDays('Expected days must be between 0 and the days in the month, in steps of 0.5.');
        }

        $row = PeriodExpectedDays::query()
            ->where('pay_period_id', $p->id)
            ->where('grade_id', $g->id)
            ->first();

        if ($row === null) {
            return PeriodExpectedDays::query()->create([
                'pay_period_id' => $p->id,
                'grade_id' => $g->id,
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

    public function for(PayPeriod $p, int $gradeId): ?float
    {
        $row = PeriodExpectedDays::query()
            ->where('pay_period_id', $p->id)
            ->where('grade_id', $gradeId)
            ->first();

        return $row === null ? null : (float) $row->days;
    }

    /** Active grades with no value for the period. @return Collection<int, Grade> */
    public function missing(PayPeriod $p): Collection
    {
        $setIds = PeriodExpectedDays::query()
            ->where('pay_period_id', $p->id)
            ->pluck('grade_id')
            ->all();

        return Grade::query()
            ->where('is_active', true)
            ->whereNotIn('id', $setIds)
            ->orderBy('rank')
            ->get();
    }

    /** Copies every grade's value from the previous period where the target has none. Returns rows added. */
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
                ->keyBy('grade_id');

            $existing = PeriodExpectedDays::query()
                ->where('pay_period_id', $p->id)
                ->pluck('grade_id')
                ->all();

            $added = 0;

            foreach ($source as $gradeId => $row) {
                if (in_array($gradeId, $existing, true)) {
                    continue;
                }

                $grade = Grade::query()->find($gradeId);
                if ($grade === null || ! $grade->is_active) {
                    continue;
                }

                PeriodExpectedDays::query()->create([
                    'pay_period_id' => $p->id,
                    'grade_id' => $gradeId,
                    'days' => $row->days,
                    'set_by' => null,
                ]);
                $added++;
            }

            return $added;
        });
    }
}
