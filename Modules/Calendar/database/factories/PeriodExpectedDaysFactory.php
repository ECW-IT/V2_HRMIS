<?php

namespace Modules\Calendar\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Calendar\Models\PayPeriod;
use Modules\Calendar\Models\PeriodExpectedDays;
use Modules\Organisation\Models\Grade;

/**
 * @extends Factory<PeriodExpectedDays>
 */
class PeriodExpectedDaysFactory extends Factory
{
    protected $model = PeriodExpectedDays::class;

    public function definition(): array
    {
        return [
            'pay_period_id' => PayPeriod::factory(),
            'grade_id' => Grade::factory(),
            'days' => fake()->randomElement([20.0, 22.0, 24.0, 24.5, 25.0, 26.0]),
            'set_by' => null,
        ];
    }
}
