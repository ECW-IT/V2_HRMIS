<?php

namespace Modules\Attendance\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Attendance\Enums\PunchResolution;
use Modules\Attendance\Models\Device;
use Modules\Attendance\Models\Punch;

/**
 * @extends Factory<Punch>
 */
class PunchFactory extends Factory
{
    protected $model = Punch::class;

    public function definition(): array
    {
        return [
            'device_id' => Device::factory(),
            'device_user_id' => fake()->numberBetween(1, 9999),
            'punched_at' => '2026-12-01 08:00:00',
            'employment_id' => null,
            'resolution' => PunchResolution::UNRESOLVED,
            'verify_mode' => 1,
            'raw_state' => 0,
            'received_at' => now(),
        ];
    }
}
