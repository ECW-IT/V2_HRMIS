<?php

namespace Modules\CoreHr\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\CoreHr\Models\Bank;

/**
 * @extends Factory<Bank>
 */
class BankFactory extends Factory
{
    protected $model = Bank::class;

    public function definition(): array
    {
        return [
            'code' => (string) fake()->unique()->numerify('####'),
            'name' => fake()->company().' Bank',
            'is_active' => true,
        ];
    }
}
