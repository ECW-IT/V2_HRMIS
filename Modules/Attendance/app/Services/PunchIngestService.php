<?php

namespace Modules\Attendance\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Attendance\Enums\PunchResolution;
use Modules\Attendance\Enums\RejectionReason;
use Modules\Attendance\Jobs\RecomputeTimecards;
use Modules\Attendance\Models\Device;
use Modules\Attendance\Models\PunchRejection;
use Modules\Attendance\Support\IngestResult;
use Modules\Attendance\Timecards\WorkDate;

final class PunchIngestService
{
    public function __construct(private readonly EnrolmentService $enrolments) {}

    public function ingest(Device $device, array $punches): IngestResult
    {
        $now = CarbonImmutable::now(config('app.timezone'));
        $receivedAt = $now->format('Y-m-d H:i:s');
        $earliest = '2020-01-01 00:00:00';
        $latestAllowed = $now->addDay()->format('Y-m-d H:i:s');

        $rejected = 0;
        $duplicatesInBatch = 0;
        $candidates = [];
        $seenKeys = [];

        foreach ($punches as $item) {
            if (! is_array($item)
                || ! array_key_exists('device_user_id', $item)
                || ! array_key_exists('punched_at', $item)
                || ! is_string($item['punched_at'])
                || preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $item['punched_at']) !== 1
            ) {
                $this->reject($device, $item, RejectionReason::MALFORMED, $receivedAt);
                $rejected++;

                continue;
            }

            $uid = $item['device_user_id'];
            if (! is_int($uid) || $uid < 1) {
                $this->reject($device, $item, RejectionReason::BAD_USER_ID, $receivedAt);
                $rejected++;

                continue;
            }

            $punchedAt = $item['punched_at'];
            if ($punchedAt < $earliest || $punchedAt > $latestAllowed) {
                $this->reject($device, $item, RejectionReason::BAD_TIMESTAMP, $receivedAt);
                $rejected++;

                continue;
            }

            $key = $device->id.'|'.$uid.'|'.$punchedAt;
            if (isset($seenKeys[$key])) {
                $duplicatesInBatch++;

                continue;
            }
            $seenKeys[$key] = true;

            $employmentId = $this->enrolments->resolve($device->device_group, $uid, $punchedAt);
            $verifyMode = $this->optionalByte($item['verify_mode'] ?? null);
            $rawState = $this->optionalByte($item['state'] ?? null);

            $candidates[] = [
                'device_id' => $device->id,
                'device_user_id' => $uid,
                'punched_at' => $punchedAt,
                'employment_id' => $employmentId,
                'resolution' => $employmentId !== null
                    ? PunchResolution::RESOLVED->value
                    : PunchResolution::UNRESOLVED->value,
                'verify_mode' => $verifyMode,
                'raw_state' => $rawState,
                'received_at' => $receivedAt,
            ];
        }

        $existingKeys = [];
        if ($candidates !== []) {
            $existing = DB::table('punches')
                ->where('device_id', $device->id)
                ->where(function ($q) use ($candidates) {
                    foreach ($candidates as $row) {
                        $q->orWhere(function ($inner) use ($row) {
                            $inner->where('device_user_id', $row['device_user_id'])
                                ->where('punched_at', $row['punched_at']);
                        });
                    }
                })
                ->get(['device_user_id', 'punched_at']);

            foreach ($existing as $row) {
                $existingKeys[$row->device_user_id.'|'.$row->punched_at] = true;
            }
        }

        $toInsert = [];
        $duplicatesExisting = 0;
        $unresolved = 0;

        foreach ($candidates as $row) {
            $k = $row['device_user_id'].'|'.$row['punched_at'];
            if (isset($existingKeys[$k])) {
                $duplicatesExisting++;

                continue;
            }
            if ($row['resolution'] === PunchResolution::UNRESOLVED->value) {
                $unresolved++;
            }
            $toInsert[] = $row;
        }

        foreach (array_chunk($toInsert, 500) as $chunk) {
            DB::table('punches')->insertOrIgnore($chunk);
        }

        $pairs = [];
        foreach ($toInsert as $row) {
            if ($row['resolution'] !== PunchResolution::RESOLVED->value || $row['employment_id'] === null) {
                continue;
            }
            $workDate = WorkDate::of(CarbonImmutable::parse($row['punched_at'], config('app.timezone')));
            $pairs[$row['employment_id'].'|'.$workDate] = [(int) $row['employment_id'], $workDate];
        }
        if ($pairs !== []) {
            RecomputeTimecards::dispatch(array_values($pairs));
        }

        $inserted = count($toInsert);
        $duplicates = $duplicatesInBatch + $duplicatesExisting;
        $received = $inserted + $duplicates + $rejected;

        $device->forceFill(['last_seen_at' => $receivedAt])->save();

        return new IngestResult($received, $inserted, $duplicates, $unresolved, $rejected);
    }

    private function reject(Device $device, mixed $payload, RejectionReason $reason, string $receivedAt): void
    {
        PunchRejection::query()->create([
            'device_id' => $device->id,
            'payload' => is_array($payload) ? $payload : ['value' => $payload],
            'reason' => $reason,
            'received_at' => $receivedAt,
        ]);
    }

    private function optionalByte(mixed $value): ?int
    {
        if (! is_int($value) || $value < 0 || $value > 255) {
            return null;
        }

        return $value;
    }
}
