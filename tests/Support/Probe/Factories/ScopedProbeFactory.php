<?php

namespace Tests\Support\Probe\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Organisation\Models\Company;
use Tests\Support\Probe\ScopedProbe;

/**
 * @extends Factory<ScopedProbe>
 */
class ScopedProbeFactory extends Factory
{
    protected $model = ScopedProbe::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => fake()->words(2, true),
            'account_no' => null,
        ];
    }
}
