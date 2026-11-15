<?php

namespace Modules\Payroll\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\CoreHr\Models\Employment;
use Modules\Payroll\Models\EmployeeComponent;
use Modules\Payroll\Models\PayComponent;

/**
 * @extends Factory<EmployeeComponent>
 */
class EmployeeComponentFactory extends Factory
{
    protected $model = EmployeeComponent::class;

    public function definition(): array
    {
        $employment = Employment::factory()->create();
        $component = PayComponent::factory()->create();

        return [
            'company_id' => $employment->company_id,
            'employment_id' => $employment->id,
            'pay_component_id' => $component->id,
            'amount' => '5000.00',
            'effective_from' => $employment->hire_date?->toDateString() ?? '2026-01-01',
            'effective_to' => null,
            'note' => null,
        ];
    }
}
