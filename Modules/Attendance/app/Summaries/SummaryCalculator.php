<?php

namespace Modules\Attendance\Summaries;

use Carbon\Carbon;
use Modules\Attendance\Enums\AttendanceMode;
use Modules\Attendance\Enums\DayType;
use Modules\Attendance\Enums\OverrideField;
use Modules\Attendance\Models\SummaryOverride;
use Modules\Attendance\Models\Timecard;
use Modules\Attendance\Services\LateWaiverService;
use Modules\Attendance\Services\TimeProfileService;
use Modules\Calendar\Models\PayPeriod;
use Modules\Calendar\Services\ExpectedDaysService;
use Modules\CoreHr\Models\Employment;
use Modules\Leave\Enums\LeaveStatus;
use Modules\Leave\Models\LeaveRequest;
use Modules\Organisation\Models\Company;

final class SummaryCalculator
{
    public function __construct(
        private readonly ExpectedDaysService $expectedDays,
        private readonly TimeProfileService $profiles,
    ) {}

    /**
     * Computes the figures from stored timecards, approved leave, expected days and overrides. Saves nothing.
     * Pass $applyOverrides false for the pre-override figure shown in a summary tooltip.
     */
    public function for(Employment $e, PayPeriod $p, bool $applyOverrides = true): SummaryFigures
    {
        [$from, $to] = $this->window($e, $p);
        $gradeId = (int) ($e->assignmentOn($to)?->grade_id ?? 0);
        $profile = $this->profiles->on($e, $to);
        $mode = $profile?->attendance_mode ?? AttendanceMode::PUNCH;
        $expected = $this->expectedDays->for($p, $gradeId);
        $expectedDays = $expected ?? 0.0;
        $employedDays = $this->inclusiveDays($from, $to);
        [$paidLeave, $unpaidLeave, $paidByDate] = $this->leave($e, $p);

        if ($mode === AttendanceMode::NO_PUNCH) {
            $worked = max(0.0, $expectedDays - $unpaidLeave);
            $credited = min($expectedDays, $worked + $paidLeave);
            $nopay = $expectedDays - $credited;
            $lateTotal = 0;
            $lateChargeable = 0;
            $full = $half = $short = $shortLeave = 0;
            $otNormal = 0;
            $extra = 0.0;
        } else {
            $cards = Timecard::query()
                ->where('employment_id', $e->id)
                ->whereDate('work_date', '>=', Carbon::parse($p->start_date)->toDateString())
                ->whereDate('work_date', '<=', Carbon::parse($p->end_date)->toDateString())
                ->get();

            $worked = 0.0;
            $full = $half = $short = $shortLeave = 0;
            $lateTotal = 0;
            $otNormal = 0;
            $dayValues = [];

            foreach ($cards as $card) {
                $worked += (float) $card->day_value;
                $full += $card->day_type === DayType::FULL ? 1 : 0;
                $half += $card->day_type === DayType::HALF ? 1 : 0;
                $short += $card->day_type === DayType::SHORT ? 1 : 0;
                $shortLeave += $card->short_leave ? 1 : 0;
                $lateTotal += LateWaiverService::chargeable($card);
                $otNormal += (int) ($card->ot_approved_minutes ?? 0);
                $dayValues[$card->work_date->toDateString()] = (float) $card->day_value;
            }

            $credited = 0.0;
            if ($from <= $to) {
                $cursor = Carbon::parse($from)->startOfDay();
                $end = Carbon::parse($to)->startOfDay();
                while ($cursor->lte($end)) {
                    $key = $cursor->toDateString();
                    $credited += min(1.0, ($dayValues[$key] ?? 0.0) + ($paidByDate[$key] ?? 0.0));
                    $cursor->addDay();
                }
            }

            $extra = max(0.0, $credited - $expectedDays);
            $nopay = max(0.0, $expectedDays - $credited);
            $grace = (int) Company::query()->findOrFail($e->company_id)->monthly_late_grace_minutes;
            $lateChargeable = max(0, $lateTotal - $grace);
        }

        $hasOverride = false;
        if ($applyOverrides) {
            $overrides = SummaryOverride::query()
                ->where('pay_period_id', $p->id)
                ->where('employment_id', $e->id)
                ->whereNull('cancelled_at')
                ->get();

            foreach ($overrides as $override) {
                $hasOverride = true;
                if ($override->field === OverrideField::NOPAY_DAYS) {
                    $nopay = (float) $override->value;
                }
                if ($override->field === OverrideField::LATE_MINUTES_CHARGEABLE) {
                    $lateChargeable = (int) $override->value;
                }
            }
        }

        return new SummaryFigures(
            gradeId: $gradeId,
            attendanceMode: $mode,
            employedDays: $employedDays,
            expectedDays: $this->days($expectedDays),
            workedDays: $this->days($worked),
            fullDays: $full,
            halfDays: $half,
            shortDays: $short,
            shortLeaveCount: $shortLeave,
            paidLeaveDays: $this->days($paidLeave),
            unpaidLeaveDays: $this->days($unpaidLeave),
            creditedDays: $this->days($credited),
            extraDays: $this->days($extra),
            nopayDays: $this->days($nopay),
            lateMinutesTotal: $lateTotal,
            lateMinutesChargeable: $lateChargeable,
            otNormalMinutes: $otNormal,
            otDoubleMinutes: 0,
            hasOverride: $hasOverride,
        );
    }

    /** @return array{0: string, 1: string} */
    private function window(Employment $e, PayPeriod $p): array
    {
        $start = Carbon::parse($p->start_date)->toDateString();
        $end = Carbon::parse($p->end_date)->toDateString();
        $hire = Carbon::parse($e->hire_date)->toDateString();
        $exit = $e->exit_date !== null ? Carbon::parse($e->exit_date)->toDateString() : $end;
        $from = max($start, $hire);
        $to = min($end, $exit);

        return [$from, $to];
    }

    private function inclusiveDays(string $from, string $to): int
    {
        if ($from > $to) {
            return 0;
        }

        $start = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->startOfDay();

        return $start->diff($end)->days + 1;
    }

    /**
     * @return array{0: float, 1: float, 2: array<string, float>}
     */
    private function leave(Employment $e, PayPeriod $p): array
    {
        $rows = LeaveRequest::query()
            ->with('leaveType')
            ->where('employment_id', $e->id)
            ->where('status', LeaveStatus::APPROVED)
            ->whereDate('date', '>=', Carbon::parse($p->start_date)->toDateString())
            ->whereDate('date', '<=', Carbon::parse($p->end_date)->toDateString())
            ->get();

        $paid = 0.0;
        $unpaid = 0.0;
        $paidByDate = [];

        foreach ($rows as $row) {
            $days = (float) $row->days;
            $key = $row->date->toDateString();
            if ($row->leaveType?->counts_as_credited) {
                $paid += $days;
                $paidByDate[$key] = ($paidByDate[$key] ?? 0.0) + $days;
            } else {
                $unpaid += $days;
            }
        }

        return [$paid, $unpaid, $paidByDate];
    }

    private function days(float $value): float
    {
        return (float) round($value, 2);
    }
}
