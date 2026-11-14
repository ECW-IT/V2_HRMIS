<?php

namespace Modules\Leave\Support;

use Carbon\Carbon;
use Modules\Attendance\Contracts\LeaveDayProvider;
use Modules\Leave\Enums\LeaveStatus;
use Modules\Leave\Models\LeaveRequest;

final class LeaveDays implements LeaveDayProvider
{
    public function forDay(int $employmentId, string $date): ?array
    {
        $day = Carbon::parse($date)->toDateString();

        $rows = LeaveRequest::withoutGlobalScopes()
            ->where('employment_id', $employmentId)
            ->whereDate('date', $day)
            ->where('status', LeaveStatus::APPROVED)
            ->orderBy('id')
            ->get();

        if ($rows->isEmpty()) {
            return null;
        }

        return [
            'leave_type_id' => (int) $rows->first()->leave_type_id,
            'days' => (float) $rows->sum('days'),
        ];
    }
}
