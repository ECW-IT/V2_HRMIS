<?php

namespace Modules\CoreHr\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\CoreHr\Models\Relationship;

/**
 * @extends Factory<Relationship>
 */
class RelationshipFactory extends Factory
{
    protected $model = Relationship::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('REL##')),
            'name' => fake()->unique()->word(),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
