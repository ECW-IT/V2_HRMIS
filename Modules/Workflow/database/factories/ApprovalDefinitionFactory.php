<?php

namespace Modules\Workflow\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Workflow\Models\ApprovalDefinition;

/**
 * @extends Factory<ApprovalDefinition>
 */
class ApprovalDefinitionFactory extends Factory
{
    protected $model = ApprovalDefinition::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->lexify('DEF_????')),
            'name' => fake()->words(3, true),
            'is_active' => true,
        ];
    }
}
