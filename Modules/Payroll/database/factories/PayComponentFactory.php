<?php

namespace Modules\Payroll\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Payroll\Enums\CalcMethod;
use Modules\Payroll\Enums\ComponentKind;
use Modules\Payroll\Models\PayComponent;

/**
 * @extends Factory<PayComponent>
 */
class PayComponentFactory extends Factory
{
    protected $model = PayComponent::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->lexify('??').fake()->numerify('##_??')),
            'name' => fake()->words(2, true),
            'kind' => ComponentKind::EARNING,
            'method' => CalcMethod::FIXED,
            'sheet' => 1,
            'epf_liable' => false,
            'taxable' => true,
            'fund' => null,
            'night_base_hours' => null,
            'is_system' => false,
            'is_active' => true,
            'display_order' => fake()->numberBetween(1, 999),
        ];
    }
}
