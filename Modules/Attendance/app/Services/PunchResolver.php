<?php

namespace Modules\Attendance\Services;

use Modules\Attendance\Enums\PunchResolution;
use Modules\Attendance\Jobs\RecomputeTimecards;
use Modules\Attendance\Models\Punch;
use Modules\Attendance\Timecards\WorkDate;

final class PunchResolver
{
    public function __construct(private readonly EnrolmentService $enrolments) {}

    /** Tries to resolve UNRESOLVED punches (optionally one device group). Returns how many became RESOLVED. */
    public function resolvePending(?string $deviceGroup = null): int
    {
        $query = Punch::query()
            ->with('device')
            ->where('resolution', PunchResolution::UNRESOLVED);

        if ($deviceGroup !== null) {
            $query->whereHas('device', fn ($q) => $q->where('device_group', $deviceGroup));
        }

        $resolved = 0;
        $pairs = [];

        foreach ($query->cursor() as $punch) {
            $employmentId = $this->enrolments->resolve(
                $punch->device->device_group,
                (int) $punch->device_user_id,
                $punch->punched_at,
            );

            if ($employmentId === null) {
                continue;
            }

            $punch->employment_id = $employmentId;
            $punch->resolution = PunchResolution::RESOLVED;
            $punch->save();
            $resolved++;

            $workDate = WorkDate::of($punch->punched_at);
            $pairs[$employmentId.'|'.$workDate] = [(int) $employmentId, $workDate];
        }

        if ($pairs !== []) {
            RecomputeTimecards::dispatch(array_values($pairs));
        }

        return $resolved;
    }
}
