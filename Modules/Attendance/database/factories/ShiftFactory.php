<?php

namespace Modules\Attendance\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Attendance\Models\Shift;

/**
 * @extends Factory<Shift>
 */
class ShiftFactory extends Factory
{
    protected $model = Shift::class;

    public function definition(): array
    {
        $n = fake()->unique()->numberBetween(1, 9999);

        return [
            'code' => 'S'.$n,
            'name' => 'Shift '.$n,
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'late_from' => '08:06:00',
            'morning_cutoff' => '10:00:00',
            'half_day_from' => '12:30:00',
            'early_leave_from' => '15:00:00',
            'is_active' => true,
        ];
    }
}
