<?php

namespace Modules\Attendance\Contracts;

interface LeaveDayProvider
{
    /** Approved leave for the employment on the date: ['leave_type_id' => int, 'days' => float] or null. Two half days of one type are summed. */
    public function forDay(int $employmentId, string $date): ?array;
}
