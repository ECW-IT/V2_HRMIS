<?php

namespace Modules\CoreHr\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\CoreHr\Models\Bank;
use Modules\CoreHr\Models\BankBranch;

/**
 * @extends Factory<BankBranch>
 */
class BankBranchFactory extends Factory
{
    protected $model = BankBranch::class;

    public function definition(): array
    {
        return [
            'bank_id' => Bank::factory(),
            'code' => (string) fake()->unique()->numerify('###'),
            'name' => fake()->city().' Branch',
        ];
    }
}
