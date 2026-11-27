<?php

namespace Modules\Attendance\Services;

use Carbon\CarbonImmutable;
use Modules\Attendance\Exceptions\InvalidTimecard;
use Modules\Attendance\Exceptions\TimecardLocked;
use Modules\Attendance\Models\Timecard;
use Modules\Calendar\Models\PayPeriod;
use Modules\Calendar\Services\PayPeriodService;
use Modules\CoreHr\Models\Employment;
use Modules\Organisation\Models\Company;

final class ManualTimecardService
{
    public function __construct(
        private readonly TimecardService $timecards,
        private readonly PayPeriodService $periods,
    ) {}

    /**
     * @param  array{
     *     work_date: string,
     *     day_type: string,
     *     day_value: string,
     *     first_in: ?string,
     *     last_out: ?string,
     *     punch_count: int,
     *     late_in_minutes: int,
     *     early_out_minutes: int,
     *     ot_morning_minutes: int,
     *     ot_evening_minutes: int,
     *     short_leave: bool,
     *     is_holiday: bool,
     *     exception: ?string
     * }  $fields
     */
    public function create(string $employeeNo, array $fields): Timecard
    {
        $employment = $this->employment($employeeNo);
        $day = $fields['work_date'];
        $this->assertOpen((int) $employment->company_id, $day);
        $this->assertFree($employment->id, $day, null);

        $period = $this->period($employment, $day);

        return Timecard::query()->create([
            'company_id' => $employment->company_id,
            'employment_id' => $employment->id,
            'work_date' => $day,
            'pay_period_id' => $period->id,
            'shift_id' => null,
            'first_in' => $fields['first_in'],
            'last_out' => $fields['last_out'],
            'punch_count' => $fields['punch_count'],
            'day_type' => $fields['day_type'],
            'day_value' => $fields['day_value'],
            'short_leave' => $fields['short_leave'],
            'late_in_minutes' => $fields['late_in_minutes'],
            'early_out_minutes' => $fields['early_out_minutes'],
            'ot_morning_minutes' => $fields['ot_morning_minutes'],
            'ot_evening_minutes' => $fields['ot_evening_minutes'],
            'is_holiday' => $fields['is_holiday'],
            'exception' => $fields['exception'],
            'computed_at' => now(),
            'late_waived' => false,
            'leave_type_id' => null,
            'leave_days' => 0,
        ]);
    }

    /**
     * @param  array{
     *     work_date: string,
     *     day_type: string,
     *     day_value: string,
     *     first_in: ?string,
     *     last_out: ?string,
     *     punch_count: int,
     *     late_in_minutes: int,
     *     early_out_minutes: int,
     *     ot_morning_minutes: int,
     *     ot_evening_minutes: int,
     *     short_leave: bool,
     *     is_holiday: bool,
     *     exception: ?string
     * }  $fields
     */
    public function update(Timecard $card, string $employeeNo, array $fields): Timecard
    {
        $employment = $this->employment($employeeNo);
        $day = $fields['work_date'];
        $this->assertOpen((int) $card->company_id, $card->work_date->toDateString());
        $this->assertOpen((int) $employment->company_id, $day);
        $this->assertFree($employment->id, $day, $card->id);

        $period = $this->period($employment, $day);

        $card->fill([
            'company_id' => $employment->company_id,
            'employment_id' => $employment->id,
            'work_date' => $day,
            'pay_period_id' => $period->id,
            'first_in' => $fields['first_in'],
            'last_out' => $fields['last_out'],
            'punch_count' => $fields['punch_count'],
            'day_type' => $fields['day_type'],
            'day_value' => $fields['day_value'],
            'short_leave' => $fields['short_leave'],
            'late_in_minutes' => $fields['late_in_minutes'],
            'early_out_minutes' => $fields['early_out_minutes'],
            'ot_morning_minutes' => $fields['ot_morning_minutes'],
            'ot_evening_minutes' => $fields['ot_evening_minutes'],
            'is_holiday' => $fields['is_holiday'],
            'exception' => $fields['exception'],
            'computed_at' => now(),
        ]);
        $card->save();

        return $card;
    }

    public function delete(Timecard $card): void
    {
        $this->assertOpen((int) $card->company_id, $card->work_date->toDateString());
        $card->delete();
    }

    private function employment(string $employeeNo): Employment
    {
        $employment = Employment::query()->where('employee_no', $employeeNo)->first();
        if ($employment === null) {
            throw new InvalidTimecard('No employee has that number.');
        }

        return $employment;
    }

    private function assertOpen(int $companyId, string $workDate): void
    {
        if ($this->timecards->isLocked($companyId, $workDate)) {
            throw new TimecardLocked('This pay period is closed.');
        }
    }

    private function assertFree(int $employmentId, string $workDate, ?int $ignoreId): void
    {
        $exists = Timecard::query()
            ->where('employment_id', $employmentId)
            ->whereDate('work_date', $workDate)
            ->when($ignoreId !== null, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists();

        if ($exists) {
            throw new InvalidTimecard('A timecard already exists for this employee on that date.');
        }
    }

    private function period(Employment $employment, string $workDate): PayPeriod
    {
        $day = CarbonImmutable::parse($workDate, config('app.timezone'));
        $company = Company::query()->findOrFail($employment->company_id);

        return $this->periods->ensure($company, (int) $day->year, (int) $day->month);
    }
}
