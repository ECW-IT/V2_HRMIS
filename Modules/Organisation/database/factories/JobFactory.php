<?php

namespace Modules\Organisation\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Organisation\Models\Job;
use Modules\Organisation\Services\JobService;

/**
 * @extends Factory<Job>
 */
class JobFactory extends Factory
{
    protected $model = Job::class;

    public function definition(): array
    {
        $name = fake()->unique()->jobTitle();

        return [
            'code' => strtoupper(fake()->unique()->lexify('???')),
            'name' => $name,
            'name_key' => JobService::nameKey($name),
            'is_active' => true,
        ];
    }
}
