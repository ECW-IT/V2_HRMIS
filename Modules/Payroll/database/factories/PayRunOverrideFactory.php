<?php

namespace Modules\Payroll\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\CoreHr\Models\Employment;
use Modules\Payroll\Models\PayComponent;
use Modules\Payroll\Models\PayRun;
use Modules\Payroll\Models\PayRunOverride;

/**
 * @extends Factory<PayRunOverride>
 */
class PayRunOverrideFactory extends Factory
{
    protected $model = PayRunOverride::class;

    public function definition(): array
    {
        $run = PayRun::factory()->create();
        $employment = Employment::factory()->create(['company_id' => $run->company_id]);
        $component = PayComponent::query()->where('code', 'FUEL')->first()
            ?? PayComponent::factory()->create(['code' => 'FUEL']);

        return [
            'pay_run_id' => $run->id,
            'employment_id' => $employment->id,
            'pay_component_id' => $component->id,
            'amount' => '1000.00',
            'reason' => 'Manual adjustment for testing',
            'set_by' => User::factory(),
            'cancelled_at' => null,
        ];
    }
}
