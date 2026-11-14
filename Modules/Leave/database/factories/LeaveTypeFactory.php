<?php

namespace Modules\Leave\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Leave\Models\LeaveType;

/**
 * @extends Factory<LeaveType>
 */
class LeaveTypeFactory extends Factory
{
    protected $model = LeaveType::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->lexify('??')),
            'name' => fake()->words(2, true),
            'counts_as_credited' => false,
            'allows_half_day' => true,
            'enforce_balance' => true,
            'requires_document' => false,
            'is_active' => true,
        ];
    }
}
