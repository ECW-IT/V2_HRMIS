<?php

namespace Modules\Payroll\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Payroll\Enums\ComponentKind;
use Modules\Payroll\Models\PayComponent;
use Modules\Payroll\Models\PayRunEmployee;
use Modules\Payroll\Models\PayRunLine;

/**
 * @extends Factory<PayRunLine>
 */
class PayRunLineFactory extends Factory
{
    protected $model = PayRunLine::class;

    public function definition(): array
    {
        $employee = PayRunEmployee::factory()->create();
        $component = PayComponent::query()->where('code', 'BASIC')->first()
            ?? PayComponent::factory()->create(['code' => 'BASIC']);

        return [
            'pay_run_employee_id' => $employee->id,
            'pay_component_id' => $component->id,
            'code' => $component->code,
            'kind' => ComponentKind::EARNING,
            'sheet' => 1,
            'quantity' => null,
            'rate' => null,
            'amount' => '1000.00',
            'source_type' => null,
            'source_id' => null,
            'is_override' => false,
            'display_order' => 10,
        ];
    }
}
