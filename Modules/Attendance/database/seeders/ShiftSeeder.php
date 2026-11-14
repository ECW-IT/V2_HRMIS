<?php

namespace Modules\Attendance\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Attendance\Models\Shift;
use Modules\Attendance\Models\ShiftBreak;

class ShiftSeeder extends Seeder
{
    public function run(): void
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = require database_path('seeders/data/shifts.php');

        foreach ($rows as $row) {
            $breaks = $row['breaks'] ?? [];
            unset($row['breaks']);

            $shift = Shift::query()->updateOrCreate(
                ['code' => $row['code']],
                [
                    'name' => $row['name'],
                    'start_time' => $row['start_time'],
                    'end_time' => $row['end_time'],
                    'late_from' => $row['late_from'],
                    'morning_cutoff' => $row['morning_cutoff'],
                    'half_day_from' => $row['half_day_from'],
                    'early_leave_from' => $row['early_leave_from'],
                    'is_active' => true,
                ],
            );

            $shift->breaks()->delete();
            foreach ($breaks as $break) {
                ShiftBreak::query()->create([
                    'shift_id' => $shift->id,
                    'name' => $break['name'],
                    'start_time' => $break['start_time'],
                    'end_time' => $break['end_time'],
                    'is_paid' => (bool) ($break['is_paid'] ?? false),
                ]);
            }
        }
    }
}
