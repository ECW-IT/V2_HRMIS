<?php

namespace Modules\Organisation\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Organisation\Enums\OrgUnitType;
use Modules\Organisation\Models\Company;
use Modules\Organisation\Models\OrgUnit;

/**
 * @extends Factory<OrgUnit>
 */
class OrgUnitFactory extends Factory
{
    protected $model = OrgUnit::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'parent_id' => null,
            'code' => strtoupper(fake()->unique()->lexify('????')),
            'name' => fake()->words(2, true),
            'type' => OrgUnitType::DEPARTMENT,
            'head_employment_id' => null,
            'is_active' => true,
        ];
    }
}
