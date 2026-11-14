<?php

namespace Modules\Calendar\Database\Factories;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Calendar\Enums\PeriodStatus;
use Modules\Calendar\Models\PayPeriod;
use Modules\Organisation\Models\Company;

/**
 * @extends Factory<PayPeriod>
 */
class PayPeriodFactory extends Factory
{
    protected $model = PayPeriod::class;

    public function definition(): array
    {
        $year = (int) fake()->numberBetween(2024, 2030);
        $month = (int) fake()->numberBetween(1, 12);
        $start = Carbon::create($year, $month, 1)->startOfDay();

        return [
            'company_id' => Company::factory(),
            'year' => $year,
            'month' => $month,
            'start_date' => $start->toDateString(),
            'end_date' => $start->copy()->endOfMonth()->toDateString(),
            'status' => PeriodStatus::OPEN,
            'closed_at' => null,
            'closed_by' => null,
            'close_version' => 0,
        ];
    }
}
