<?php

namespace Modules\Payroll\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\CoreHr\Models\Employment;
use Modules\Payroll\Models\YtdOpening;

/**
 * @extends Factory<YtdOpening>
 */
class YtdOpeningFactory extends Factory
{
    protected $model = YtdOpening::class;

    public function definition(): array
    {
        $employment = Employment::factory()->create();

        return [
            'company_id' => $employment->company_id,
            'employment_id' => $employment->id,
            'tax_year_start' => '2026-04-01',
            'months_covered' => 8,
            'gross' => '0.00',
            'taxable_pay' => '0.00',
            'tax' => '0.00',
            'epf_employee' => '0.00',
            'epf_employer' => '0.00',
            'etf' => '0.00',
            'note' => null,
            'set_by' => User::factory(),
        ];
    }
}
