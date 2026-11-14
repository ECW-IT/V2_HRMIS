<?php

// From the current system's records (see 06_phase_2_plan.md A11). Confirm the times with HR before go-live.

return [
    [
        'code' => 'D0800',
        'name' => 'Day 08:00–17:00',
        'start_time' => '08:00',
        'end_time' => '17:00',
        'late_from' => '08:06',
        'morning_cutoff' => '10:00',
        'half_day_from' => '12:30',
        'early_leave_from' => '15:00',
        'breaks' => [
            ['name' => 'Dinner', 'start_time' => '20:00', 'end_time' => '20:30', 'is_paid' => false],
        ],
    ],
    [
        'code' => 'D0830',
        'name' => 'Day 08:30–17:30',
        'start_time' => '08:30',
        'end_time' => '17:30',
        'late_from' => '08:36',
        'morning_cutoff' => '10:30',
        'half_day_from' => '12:30',
        'early_leave_from' => '15:30',
        'breaks' => [
            ['name' => 'Evening snack', 'start_time' => '18:00', 'end_time' => '18:15', 'is_paid' => false],
            ['name' => 'Dinner', 'start_time' => '20:00', 'end_time' => '20:30', 'is_paid' => false],
        ],
    ],
    [
        'code' => 'D0730',
        'name' => 'Day 07:30–16:30',
        'start_time' => '07:30',
        'end_time' => '16:30',
        'late_from' => '07:36',
        'morning_cutoff' => '09:30',
        'half_day_from' => '12:00',
        'early_leave_from' => '14:30',
        'breaks' => [
            ['name' => 'Evening snack', 'start_time' => '18:00', 'end_time' => '18:15', 'is_paid' => false],
            ['name' => 'Dinner', 'start_time' => '20:00', 'end_time' => '20:30', 'is_paid' => false],
        ],
    ],
    [
        'code' => 'D0700',
        'name' => 'Day 07:00–16:00',
        'start_time' => '07:00',
        'end_time' => '16:00',
        'late_from' => '07:06',
        'morning_cutoff' => '10:00',
        'half_day_from' => '12:30',
        'early_leave_from' => '15:00',
        'breaks' => [],
    ],
];
