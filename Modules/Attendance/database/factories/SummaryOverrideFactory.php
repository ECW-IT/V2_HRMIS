<?php

namespace Modules\Attendance\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Attendance\Enums\OverrideField;
use Modules\Attendance\Models\SummaryOverride;
use Modules\Calendar\Services\PayPeriodService;
use Modules\CoreHr\Models\Employment;
use Modules\Organisation\Models\Company;

/**
 * @extends Factory<SummaryOverride>
 */
class SummaryOverrideFactory extends Factory
{
    protected $model = SummaryOverride::class;

    public function definition(): array
    {
        $company = Company::factory()->create();
        $employment = Employment::factory()->create(['company_id' => $company->id]);
        $period = app(PayPeriodService::class)->ensure($company, 2026, 12);

        return [
            'pay_period_id' => $period->id,
            'employment_id' => $employment->id,
            'field' => OverrideField::NOPAY_DAYS,
            'value' => 1,
            'reason' => 'Factory override for a shutdown',
            'set_by' => User::factory(),
            'cancelled_at' => null,
        ];
    }
}
