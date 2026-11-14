<?php

namespace Modules\Attendance\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Attendance\Enums\AttendanceMode;
use Modules\Attendance\Models\PeriodTimeSummary;
use Modules\Calendar\Services\PayPeriodService;
use Modules\CoreHr\Models\Employment;
use Modules\Organisation\Models\Company;
use Modules\Organisation\Models\Grade;

/**
 * @extends Factory<PeriodTimeSummary>
 */
class PeriodTimeSummaryFactory extends Factory
{
    protected $model = PeriodTimeSummary::class;

    public function definition(): array
    {
        $company = Company::factory()->create();
        $employment = Employment::factory()->create(['company_id' => $company->id]);
        $period = app(PayPeriodService::class)->ensure($company, 2026, 12);

        return [
            'company_id' => $company->id,
            'pay_period_id' => $period->id,
            'employment_id' => $employment->id,
            'version' => 1,
            'grade_id' => Grade::factory(),
            'attendance_mode' => AttendanceMode::PUNCH,
            'employed_days' => 31,
            'expected_days' => 25,
            'worked_days' => 25,
            'full_days' => 25,
            'half_days' => 0,
            'short_days' => 0,
            'short_leave_count' => 0,
            'paid_leave_days' => 0,
            'unpaid_leave_days' => 0,
            'credited_days' => 25,
            'extra_days' => 0,
            'nopay_days' => 0,
            'late_minutes_total' => 0,
            'late_minutes_chargeable' => 0,
            'ot_normal_minutes' => 0,
            'ot_double_minutes' => 0,
            'has_override' => false,
            'created_at' => now(),
        ];
    }
}
