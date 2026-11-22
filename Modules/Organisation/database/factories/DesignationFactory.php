<?php

namespace Modules\Organisation\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Organisation\Models\Designation;
use Modules\Organisation\Services\DesignationService;

/**
 * @extends Factory<Designation>
 */
class DesignationFactory extends Factory
{
    protected $model = Designation::class;

    public function definition(): array
    {
        $name = fake()->unique()->jobTitle();

        return [
            'code' => strtoupper(fake()->unique()->lexify('???')),
            'name' => $name,
            'name_key' => DesignationService::nameKey($name),
            'is_active' => true,
        ];
    }
}
