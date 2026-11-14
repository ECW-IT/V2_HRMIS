<?php

namespace Modules\Attendance\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Attendance\Models\Shift;
use Modules\Attendance\Models\ShiftBreak;

/**
 * @extends Factory<ShiftBreak>
 */
class ShiftBreakFactory extends Factory
{
    protected $model = ShiftBreak::class;

    public function definition(): array
    {
        return [
            'shift_id' => Shift::factory(),
            'name' => 'Dinner',
            'start_time' => '20:00:00',
            'end_time' => '20:30:00',
            'is_paid' => false,
        ];
    }
}
