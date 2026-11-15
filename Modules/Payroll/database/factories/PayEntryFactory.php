<?php

namespace Modules\Payroll\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Calendar\Models\PayPeriod;
use Modules\CoreHr\Models\Employment;
use Modules\Payroll\Enums\CalcMethod;
use Modules\Payroll\Enums\PayEntryStatus;
use Modules\Payroll\Models\PayComponent;
use Modules\Payroll\Models\PayEntry;

/**
 * @extends Factory<PayEntry>
 */
class PayEntryFactory extends Factory
{
    protected $model = PayEntry::class;

    public function definition(): array
    {
        $employment = Employment::factory()->create();
        $period = PayPeriod::factory()->create(['company_id' => $employment->company_id]);
        $component = PayComponent::factory()->create([
            'method' => CalcMethod::ENTRY,
            'is_active' => true,
        ]);

        return [
            'company_id' => $employment->company_id,
            'pay_period_id' => $period->id,
            'employment_id' => $employment->id,
            'pay_component_id' => $component->id,
            'quantity' => null,
            'amount' => '1000.00',
            'reference' => null,
            'note' => null,
            'status' => PayEntryStatus::ACTIVE,
            'created_by' => User::factory(),
            'cancelled_by' => null,
            'cancelled_at' => null,
        ];
    }
}
