<?php

namespace Tests\Support;

use App\Models\User;
use Carbon\Carbon;
use Modules\Attendance\Enums\PunchResolution;
use Modules\Attendance\Models\Punch;
use Modules\Attendance\Services\PeriodCloseService;
use Modules\Attendance\Services\TimecardService;
use Modules\Attendance\Summaries\SummaryCalculator;
use Modules\Attendance\Summaries\SummaryFigures;
use Modules\Attendance\Support\ChecklistItem;
use Modules\Calendar\Enums\PeriodStatus;
use Modules\Calendar\Models\PayPeriod;
use Modules\Calendar\Services\ExpectedDaysService;
use Modules\Calendar\Services\PayPeriodService;
use Modules\CoreHr\Models\Employment;
use Modules\Leave\Enums\LeavePortion;
use Modules\Leave\Models\LeavePolicy;
use Modules\Leave\Models\LeaveType;
use Modules\Leave\Services\LeaveService;
use Modules\Organisation\Models\Company;
use Modules\Organisation\Models\Grade;

trait PeriodSummaryHelpers
{
    /** Punches in/out and computes, for each date. $days: ['2026-12-01' => ['08:00', '17:00'], ...] ; a value of null = no punches. */
    protected function work(Employment $e, array $days): void
    {
        $timecards = app(TimecardService::class);

        foreach ($days as $date => $times) {
            if ($times !== null) {
                foreach ($times as $time) {
                    Punch::factory()->create([
                        'employment_id' => $e->id,
                        'resolution' => PunchResolution::RESOLVED,
                        'punched_at' => $date.' '.$time.':00',
                    ]);
                }
            }

            $timecards->compute($e, $date);
        }
    }

    /** N consecutive full days (08:00–17:00) starting on $from. */
    protected function fullDays(Employment $e, string $from, int $n): void
    {
        $days = [];
        $cursor = Carbon::parse($from)->startOfDay();

        for ($i = 0; $i < $n; $i++) {
            $days[$cursor->toDateString()] = ['08:00', '17:00'];
            $cursor->addDay();
        }

        $this->work($e, $days);
    }

    /** Ensures 2026-12; while it is open and the grade has no value, sets expected days 25. */
    protected function dec(Employment $e): PayPeriod
    {
        $period = app(PayPeriodService::class)->ensure($e->company, 2026, 12);

        if ($period->status !== PeriodStatus::OPEN) {
            return $period;
        }

        $start = Carbon::parse($period->start_date)->toDateString();
        $end = Carbon::parse($period->end_date)->toDateString();
        $hire = Carbon::parse($e->hire_date)->toDateString();
        $exit = $e->exit_date !== null ? Carbon::parse($e->exit_date)->toDateString() : $end;
        $from = max($start, $hire);
        $to = min($end, $exit);
        $on = $from <= $to ? $to : $end;
        $gradeId = $e->assignmentOn($on)?->grade_id;

        if ($gradeId !== null && app(ExpectedDaysService::class)->for($period, (int) $gradeId) === null) {
            $grade = Grade::query()->findOrFail($gradeId);
            app(ExpectedDaysService::class)->set($period, $grade, 25);
        }

        return $period;
    }

    protected function fig(Employment $e): SummaryFigures
    {
        return app(SummaryCalculator::class)->for($e, $this->dec($e));
    }

    protected function setExpected(Employment $e, float $days, string $on = '2026-12-31'): void
    {
        $period = app(PayPeriodService::class)->ensure($e->company, 2026, 12);
        $grade = Grade::query()->findOrFail($e->assignmentOn($on)->grade_id);
        app(ExpectedDaysService::class)->set($period, $grade, $days);
    }

    protected function hr(Employment $e): User
    {
        return User::factory()
            ->forCompanies([$e->company])
            ->withRole('hr-manager')
            ->create(['must_change_password' => false]);
    }

    protected function admin(Employment $e): User
    {
        return User::factory()
            ->forCompanies([$e->company])
            ->withRole('super-admin')
            ->create(['must_change_password' => false]);
    }

    protected function punch(Employment $e, string $at): Punch
    {
        return Punch::factory()->create([
            'employment_id' => $e->id,
            'resolution' => PunchResolution::RESOLVED,
            'punched_at' => $at,
        ]);
    }

    /** @param  list<string>  $dates */
    protected function leave(Employment $e, string $code, array $dates, LeavePortion $portion = LeavePortion::FULL): void
    {
        $typeId = (int) LeaveType::query()->where('code', $code)->value('id');
        $previous = Carbon::getTestNow();
        Carbon::setTestNow('2026-12-20 09:00:00');

        try {
            $hr = $this->hr($e);
            foreach ($dates as $date) {
                app(LeaveService::class)->request($e, $typeId, $date, $portion, $hr, ['pre_approved' => true]);
            }
        } finally {
            Carbon::setTestNow($previous);
        }
    }

    protected function paidType(string $code): void
    {
        $type = LeaveType::query()->create([
            'code' => $code,
            'name' => $code.' leave',
            'counts_as_credited' => true,
            'allows_half_day' => true,
            'enforce_balance' => false,
            'requires_document' => false,
            'is_active' => true,
        ]);

        LeavePolicy::query()->create([
            'leave_type_id' => $type->id,
            'company_id' => null,
            'grade_id' => null,
            'employment_type' => null,
            'days_per_period' => 10,
            'effective_from' => '2026-01-01',
            'effective_to' => null,
        ]);
    }

    protected function clean(Company $c): Employment
    {
        $e = $this->profiled('2026-01-01', $c);
        $this->setExpected($e, 25);
        $this->fullDays($e, '2026-12-01', 25);

        return $e->fresh();
    }

    protected function item(PayPeriod $p, string $code): ChecklistItem
    {
        foreach (app(PeriodCloseService::class)->checklist($p) as $item) {
            if ($item->code === $code) {
                return $item;
            }
        }

        throw new \RuntimeException('Checklist item '.$code.' was not returned.');
    }
}
