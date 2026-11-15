<?php

namespace Modules\Payroll\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Payroll\Models\TaxSlab;

/**
 * @extends Factory<TaxSlab>
 */
class TaxSlabFactory extends Factory
{
    protected $model = TaxSlab::class;

    public function definition(): array
    {
        return [
            'effective_from' => '2026-01-01',
            'effective_to' => null,
            'sequence' => 1,
            'width' => '150000.00',
            'percent' => '0.00',
        ];
    }
}
