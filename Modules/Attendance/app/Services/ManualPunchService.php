<?php

namespace Modules\Attendance\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Modules\Attendance\Enums\PunchResolution;
use Modules\Attendance\Exceptions\InvalidPunch;
use Modules\Attendance\Jobs\RecomputeTimecards;
use Modules\Attendance\Models\Device;
use Modules\Attendance\Models\Punch;
use Modules\Attendance\Timecards\WorkDate;

final class ManualPunchService
{
    public function __construct(
        private readonly PunchIngestService $ingest,
        private readonly EnrolmentService $enrolments,
    ) {}

    public function create(Device $device, int $deviceUserId, string $punchedAt, ?int $verifyMode, ?int $state): void
    {
        $result = $this->ingest->ingest($device, [[
            'device_user_id' => $deviceUserId,
            'punched_at' => $punchedAt,
            'verify_mode' => $verifyMode,
            'state' => $state,
        ]]);

        if ($result->inserted === 1) {
            return;
        }

        if ($result->duplicates > 0) {
            throw new InvalidPunch('A punch already exists for this device, fingerprint id, and time.');
        }

        throw new InvalidPunch('That time cannot be stored. Use a time after 2020 that is not more than a day ahead.');
    }

    public function update(Punch $punch, Device $device, int $deviceUserId, string $punchedAt, ?int $verifyMode, ?int $state): void
    {
        $oldEmploymentId = $punch->employment_id !== null ? (int) $punch->employment_id : null;
        $oldWorkDate = $oldEmploymentId !== null ? WorkDate::of($punch->punched_at) : null;

        $duplicate = Punch::query()
            ->where('device_id', $device->id)
            ->where('device_user_id', $deviceUserId)
            ->where('punched_at', $punchedAt)
            ->whereKeyNot($punch->id)
            ->exists();

        if ($duplicate) {
            throw new InvalidPunch('A punch already exists for this device, fingerprint id, and time.');
        }

        $employmentId = $this->enrolments->resolve($device->device_group, $deviceUserId, $punchedAt);

        $punch->allowManualChange = true;
        $punch->device_id = $device->id;
        $punch->device_user_id = $deviceUserId;
        $punch->punched_at = $punchedAt;
        $punch->verify_mode = $verifyMode;
        $punch->raw_state = $state;
        $punch->employment_id = $employmentId;
        $punch->resolution = $employmentId !== null
            ? PunchResolution::RESOLVED
            : PunchResolution::UNRESOLVED;

        try {
            $punch->save();
        } catch (QueryException) {
            throw new InvalidPunch('A punch already exists for this device, fingerprint id, and time.');
        }

        $at = CarbonImmutable::parse($punchedAt, config('app.timezone'));
        $this->recompute($oldEmploymentId, $oldWorkDate, $employmentId, WorkDate::of($at));
    }

    public function delete(Punch $punch): void
    {
        $employmentId = $punch->employment_id !== null ? (int) $punch->employment_id : null;
        $workDate = $employmentId !== null ? WorkDate::of($punch->punched_at) : null;

        $punch->allowManualChange = true;
        $punch->delete();

        if ($employmentId !== null && $workDate !== null) {
            RecomputeTimecards::dispatch([[$employmentId, $workDate]]);
        }
    }

    private function recompute(?int $oldEmploymentId, ?string $oldWorkDate, ?int $newEmploymentId, string $newWorkDate): void
    {
        $pairs = [];

        if ($oldEmploymentId !== null && $oldWorkDate !== null) {
            $pairs[$oldEmploymentId.'|'.$oldWorkDate] = [$oldEmploymentId, $oldWorkDate];
        }

        if ($newEmploymentId !== null) {
            $pairs[$newEmploymentId.'|'.$newWorkDate] = [$newEmploymentId, $newWorkDate];
        }

        if ($pairs !== []) {
            RecomputeTimecards::dispatch(array_values($pairs));
        }
    }
}
