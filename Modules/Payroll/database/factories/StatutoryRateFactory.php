<?php

namespace Modules\Payroll\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Payroll\Models\StatutoryRate;

/**
 * @extends Factory<StatutoryRate>
 */
class StatutoryRateFactory extends Factory
{
    protected $model = StatutoryRate::class;

    public function definition(): array
    {
        return [
            'code' => 'EPF_EE',
            'percent' => '8.00',
            'effective_from' => '2026-01-01',
            'effective_to' => null,
        ];
    }
}
