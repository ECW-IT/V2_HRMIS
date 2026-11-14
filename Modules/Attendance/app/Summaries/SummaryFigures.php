<?php

namespace Modules\Attendance\Summaries;

use Modules\Attendance\Enums\AttendanceMode;

final readonly class SummaryFigures
{
    public function __construct(
        public int $gradeId,
        public AttendanceMode $attendanceMode,
        public int $employedDays,
        public float $expectedDays,
        public float $workedDays,
        public int $fullDays,
        public int $halfDays,
        public int $shortDays,
        public int $shortLeaveCount,
        public float $paidLeaveDays,
        public float $unpaidLeaveDays,
        public float $creditedDays,
        public float $extraDays,
        public float $nopayDays,
        public int $lateMinutesTotal,
        public int $lateMinutesChargeable,
        public int $otNormalMinutes,
        public int $otDoubleMinutes,
        public bool $hasOverride,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'grade_id' => $this->gradeId,
            'attendance_mode' => $this->attendanceMode->value,
            'employed_days' => $this->employedDays,
            'expected_days' => $this->expectedDays,
            'worked_days' => $this->workedDays,
            'full_days' => $this->fullDays,
            'half_days' => $this->halfDays,
            'short_days' => $this->shortDays,
            'short_leave_count' => $this->shortLeaveCount,
            'paid_leave_days' => $this->paidLeaveDays,
            'unpaid_leave_days' => $this->unpaidLeaveDays,
            'credited_days' => $this->creditedDays,
            'extra_days' => $this->extraDays,
            'nopay_days' => $this->nopayDays,
            'late_minutes_total' => $this->lateMinutesTotal,
            'late_minutes_chargeable' => $this->lateMinutesChargeable,
            'ot_normal_minutes' => $this->otNormalMinutes,
            'ot_double_minutes' => $this->otDoubleMinutes,
            'has_override' => $this->hasOverride,
        ];
    }
}
