<?php

namespace Modules\Payroll\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\CoreHr\Models\Employment;
use Modules\Payroll\Enums\ArrearsKind;
use Modules\Payroll\Models\ArrearsEntry;

/**
 * @extends Factory<ArrearsEntry>
 */
class ArrearsEntryFactory extends Factory
{
    protected $model = ArrearsEntry::class;

    public function definition(): array
    {
        $employment = Employment::factory()->create();

        return [
            'company_id' => $employment->company_id,
            'employment_id' => $employment->id,
            'kind' => ArrearsKind::SHORTFALL,
            'amount' => '1000.00',
            'pay_run_id' => null,
            'pay_period_id' => null,
            'note' => null,
            'created_by' => User::factory(),
            'created_at' => now(),
        ];
    }
}
