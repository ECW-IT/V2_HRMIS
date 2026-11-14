<?php

namespace Modules\Attendance\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Modules\Attendance\Enums\PunchResolution;
use Modules\Attendance\Enums\TimecardException;
use Modules\Attendance\Exceptions\PeriodCloseBlocked;
use Modules\Attendance\Models\PeriodEvent;
use Modules\Attendance\Models\PeriodTimeSummary;
use Modules\Attendance\Models\Punch;
use Modules\Attendance\Models\SummaryOverride;
use Modules\Attendance\Models\Timecard;
use Modules\Attendance\Summaries\SummaryCalculator;
use Modules\Attendance\Support\ChecklistItem;
use Modules\Attendance\Timecards\WorkDate;
use Modules\Calendar\Enums\PeriodStatus;
use Modules\Calendar\Exceptions\InvalidPeriodAction;
use Modules\Calendar\Models\PayPeriod;
use Modules\Calendar\Services\ExpectedDaysService;
use Modules\Calendar\Services\PayPeriodService;
use Modules\CoreHr\Models\Employment;
use Modules\Leave\Services\LeaveService;

final class PeriodCloseService
{
    public function __construct(
        private readonly SummaryService $summaries,
        private readonly SummaryCalculator $calculator,
        private readonly TimecardService $timecards,
        private readonly PayPeriodService $periods,
        private readonly ExpectedDaysService $expectedDays,
        private readonly TimeProfileService $profiles,
        private readonly AdjustmentService $adjustments,
        private readonly OtApprovalService $overtime,
        private readonly LeaveService $leave,
    ) {}

    /** @return ChecklistItem[] every item, in code order, including those with count 0 */
    public function checklist(PayPeriod $p): array
    {
        $employments = $this->summaries->employmentsIn($p);

        $today = Carbon::now(config('app.timezone'))->startOfDay();
        $end = Carbon::parse($p->end_date)->startOfDay();
        $c01 = $today->lte($end) ? 1 : 0;

        $previous = $this->periods->previous($p);
        $c02 = ($previous !== null && $previous->status === PeriodStatus::OPEN) ? 1 : 0;

        $gradesMissing = [];
        $withoutProfile = 0;
        foreach ($employments as $employment) {
            $to = $this->lastEmployedDay($employment, $p);
            $gradeId = (int) ($employment->assignmentOn($to)?->grade_id ?? 0);
            if ($gradeId !== 0 && $this->expectedDays->for($p, $gradeId) === null) {
                $gradesMissing[$gradeId] = true;
            }
            if ($this->profiles->on($employment, $to) === null) {
                $withoutProfile++;
            }
        }

        $c05 = Timecard::query()
            ->where('pay_period_id', $p->id)
            ->whereIn('exception', [TimecardException::MISSING_PUNCH->value, TimecardException::NO_PROFILE->value])
            ->count();

        $startDate = Carbon::parse($p->start_date)->toDateString();
        $endDate = Carbon::parse($p->end_date)->toDateString();
        [$windowStart] = WorkDate::window($startDate);
        [, $windowEnd] = WorkDate::window($endDate);
        $c09 = Punch::query()
            ->where('resolution', PunchResolution::UNRESOLVED)
            ->where('punched_at', '>=', $windowStart)
            ->where('punched_at', '<', $windowEnd)
            ->count();

        $w01 = Timecard::query()
            ->where('pay_period_id', $p->id)
            ->where('exception', TimecardException::SHORT_PRESENCE->value)
            ->count();

        $w02 = SummaryOverride::query()
            ->where('pay_period_id', $p->id)
            ->whereNull('cancelled_at')
            ->distinct()
            ->pluck('employment_id')
            ->count();

        return [
            $this->item('C01', 'The period has ended', true, $c01),
            $this->item('C02', 'The previous period is closed', true, $c02),
            $this->item('C03', 'Expected working days are set for every grade in use', true, count($gradesMissing)),
            $this->item('C04', 'Every employee has a time profile', true, $withoutProfile),
            $this->item('C05', 'No missing punches', true, $c05),
            $this->item('C06', 'No attendance adjustments are waiting', true, $this->adjustments->pendingCount($p)),
            $this->item('C07', 'All overtime is decided', true, $this->overtime->undecidedCount($p)),
            $this->item('C08', 'No leave requests are waiting', true, $this->leave->pendingCount($p)),
            $this->item('C09', 'No unidentified punches', true, $c09),
            $this->item('W01', 'Days with too little presence', false, $w01),
            $this->item('W02', 'Employees with overrides', false, $w02),
        ];
    }

    public function close(PayPeriod $p, User $by): PayPeriod
    {
        $this->authorize($by, 'period.close', (int) $p->company_id);

        $this->timecards->computeRange((int) $p->company_id, $p->start_date, $p->end_date);

        $blocking = array_values(array_filter(
            $this->checklist($p),
            fn (ChecklistItem $item) => $item->blocking && ! $item->ok,
        ));

        if ($blocking !== []) {
            throw new PeriodCloseBlocked($blocking);
        }

        return DB::transaction(function () use ($p, $by) {
            $closed = $this->periods->markClosed($p, $by);
            $version = (int) $closed->close_version;

            foreach ($this->summaries->employmentsIn($closed) as $employment) {
                $figures = $this->calculator->for($employment, $closed);
                PeriodTimeSummary::query()->create([
                    'company_id' => $employment->company_id,
                    'pay_period_id' => $closed->id,
                    'employment_id' => $employment->id,
                    'version' => $version,
                    'created_at' => now(),
                    ...$figures->toArray(),
                ]);
            }

            PeriodEvent::query()->create([
                'pay_period_id' => $closed->id,
                'event' => 'CLOSED',
                'version' => $version,
                'reason' => null,
                'user_id' => $by->id,
                'created_at' => now(),
            ]);

            return $closed;
        });
    }

    public function reopen(PayPeriod $p, User $by, string $reason): PayPeriod
    {
        $this->authorize($by, 'period.reopen', (int) $p->company_id);

        $reason = trim($reason);
        if (mb_strlen($reason) < 10) {
            throw new InvalidPeriodAction('A reason of at least 10 characters is required.');
        }

        return DB::transaction(function () use ($p, $by, $reason) {
            $open = $this->periods->markOpen($p);

            PeriodEvent::query()->create([
                'pay_period_id' => $open->id,
                'event' => 'REOPENED',
                'version' => (int) $open->close_version,
                'reason' => $reason,
                'user_id' => $by->id,
                'created_at' => now(),
            ]);

            return $open;
        });
    }

    private function item(string $code, string $title, bool $blocking, int $count): ChecklistItem
    {
        return new ChecklistItem($code, $title, $blocking, $count, $count === 0);
    }

    private function lastEmployedDay(Employment $e, PayPeriod $p): string
    {
        $start = Carbon::parse($p->start_date)->toDateString();
        $end = Carbon::parse($p->end_date)->toDateString();
        $hire = Carbon::parse($e->hire_date)->toDateString();
        $exit = $e->exit_date !== null ? Carbon::parse($e->exit_date)->toDateString() : $end;

        return min($end, $exit) < max($start, $hire) ? $end : min($end, $exit);
    }

    private function authorize(User $by, string $permission, int $companyId): void
    {
        if (! $by->can($permission) || ! $this->canAccessCompany($by, $companyId)) {
            throw new AuthorizationException('This action is unauthorized.');
        }
    }

    private function canAccessCompany(User $by, int $companyId): bool
    {
        if ($by->all_companies) {
            return true;
        }

        return $by->companies()->where('companies.id', $companyId)->exists();
    }
}
