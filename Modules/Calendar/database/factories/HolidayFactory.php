<?php

namespace Modules\Calendar\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Calendar\Enums\HolidayKind;
use Modules\Calendar\Models\Holiday;
use Modules\Organisation\Models\Company;

/**
 * @extends Factory<Holiday>
 */
class HolidayFactory extends Factory
{
    protected $model = Holiday::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'date' => fake()->unique()->date(),
            'name' => fake()->words(3, true),
            'kind' => HolidayKind::COMPANY,
        ];
    }
}
