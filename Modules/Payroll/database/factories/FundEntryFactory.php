<?php

namespace Modules\Payroll\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\CoreHr\Models\Employment;
use Modules\Payroll\Enums\FundCode;
use Modules\Payroll\Enums\FundEntryKind;
use Modules\Payroll\Models\FundEntry;

/**
 * @extends Factory<FundEntry>
 */
class FundEntryFactory extends Factory
{
    protected $model = FundEntry::class;

    public function definition(): array
    {
        $employment = Employment::factory()->create();

        return [
            'company_id' => $employment->company_id,
            'employment_id' => $employment->id,
            'fund' => FundCode::SAVINGS,
            'kind' => FundEntryKind::CONTRIBUTION,
            'amount' => '1000.00',
            'pay_run_id' => null,
            'pay_period_id' => null,
            'note' => null,
            'created_by' => User::factory(),
            'created_at' => now(),
        ];
    }
}
