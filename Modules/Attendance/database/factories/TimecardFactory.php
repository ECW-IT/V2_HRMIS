<?php

namespace Modules\Attendance\Database\Factories;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Attendance\Enums\DayType;
use Modules\Attendance\Models\Timecard;
use Modules\Calendar\Services\PayPeriodService;
use Modules\CoreHr\Models\Employment;
use Modules\Organisation\Models\Company;

/**
 * @extends Factory<Timecard>
 */
class TimecardFactory extends Factory
{
    protected $model = Timecard::class;

    public function definition(): array
    {
        $company = Company::factory()->create();
        $employment = Employment::factory()->create(['company_id' => $company->id]);
        $workDate = '2026-12-01';
        $period = app(PayPeriodService::class)->ensure($company, 2026, 12);

        return [
            'company_id' => $company->id,
            'employment_id' => $employment->id,
            'work_date' => $workDate,
            'pay_period_id' => $period->id,
            'shift_id' => null,
            'first_in' => null,
            'last_out' => null,
            'punch_count' => 0,
            'day_type' => DayType::NONE,
            'day_value' => 0,
            'short_leave' => false,
            'late_in_minutes' => 0,
            'early_out_minutes' => 0,
            'ot_morning_minutes' => 0,
            'ot_evening_minutes' => 0,
            'is_holiday' => false,
            'exception' => null,
            'computed_at' => Carbon::now(),
            'late_waived' => false,
            'ot_approved_minutes' => null,
            'ot_decided_by' => null,
            'ot_decided_at' => null,
            'leave_type_id' => null,
            'leave_days' => 0,
        ];
    }
}
