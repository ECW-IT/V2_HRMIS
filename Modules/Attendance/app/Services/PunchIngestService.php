<?php

namespace Modules\Attendance\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Attendance\Enums\PunchResolution;
use Modules\Attendance\Enums\RejectionReason;
use Modules\Attendance\Jobs\RecomputeTimecards;
use Modules\Attendance\Models\Device;
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
        $lookups = [];
        $rejectionRows = [];

        foreach ($punches as $item) {
            $payload = $item;
            $item = $this->normalize($item);

            if (! is_array($item)
                || ! array_key_exists('device_user_id', $item)
                || ! array_key_exists('punched_at', $item)
                || ! is_string($item['punched_at'])
                || preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $item['punched_at']) !== 1
            ) {
                $rejectionRows[] = $this->rejectionRow($device, $payload, RejectionReason::MALFORMED, $receivedAt);
                $rejected++;

                continue;
            }

            $uid = $item['device_user_id'];
            if (! is_int($uid) || $uid < 1) {
                $rejectionRows[] = $this->rejectionRow($device, $payload, RejectionReason::BAD_USER_ID, $receivedAt);
                $rejected++;

                continue;
            }

            $punchedAt = $item['punched_at'];
            if ($punchedAt < $earliest || $punchedAt > $latestAllowed) {
                $rejectionRows[] = $this->rejectionRow($device, $payload, RejectionReason::BAD_TIMESTAMP, $receivedAt);
                $rejected++;

                continue;
            }

            $key = $device->id.'|'.$uid.'|'.$punchedAt;
            if (isset($seenKeys[$key])) {
                $duplicatesInBatch++;

                continue;
            }
            $seenKeys[$key] = true;

            $lookups[] = [$uid, $punchedAt];
            $verifyMode = $this->optionalByte($item['verify_mode'] ?? null);
            $rawState = $this->optionalByte($item['state'] ?? null);

            $candidates[] = [
                'device_id' => $device->id,
                'device_user_id' => $uid,
                'punched_at' => $punchedAt,
                'employment_id' => null,
                'resolution' => PunchResolution::UNRESOLVED->value,
                'verify_mode' => $verifyMode,
                'raw_state' => $rawState,
                'received_at' => $receivedAt,
            ];
        }

        foreach (array_chunk($rejectionRows, 500) as $chunk) {
            DB::table('punch_rejections')->insert($chunk);
        }

        $resolved = $this->enrolments->resolveMany($device->device_group, $lookups);
        foreach ($candidates as $i => $row) {
            $mapKey = $row['device_user_id'].'|'.substr($row['punched_at'], 0, 10);
            $employmentId = $resolved[$mapKey] ?? null;
            if ($employmentId !== null) {
                $candidates[$i]['employment_id'] = $employmentId;
                $candidates[$i]['resolution'] = PunchResolution::RESOLVED->value;
            }
        }

        $existingKeys = [];
        if ($candidates !== []) {
            $uids = array_values(array_unique(array_map(fn (array $row) => $row['device_user_id'], $candidates)));
            $times = array_values(array_unique(array_map(fn (array $row) => $row['punched_at'], $candidates)));

            $existing = DB::table('punches')
                ->where('device_id', $device->id)
                ->whereIn('device_user_id', $uids)
                ->whereIn('punched_at', $times)
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

    /**
     * @return array{device_id: int, payload: string, reason: string, received_at: string}
     */
    private function rejectionRow(Device $device, mixed $payload, RejectionReason $reason, string $receivedAt): array
    {
        return [
            'device_id' => $device->id,
            'payload' => json_encode(is_array($payload) ? $payload : ['value' => $payload], JSON_THROW_ON_ERROR),
            'reason' => $reason->value,
            'received_at' => $receivedAt,
        ];
    }

    /**
     * Fingerprint terminals post ZKTeco fields: id, timestamp, state, type.
     * The stored punch uses device_user_id, punched_at, state, verify_mode.
     */
    private function normalize(mixed $item): mixed
    {
        if (! is_array($item)) {
            return $item;
        }

        if (! array_key_exists('device_user_id', $item) && array_key_exists('id', $item)) {
            $item['device_user_id'] = $this->integerOrOriginal($item['id']);
        } elseif (array_key_exists('device_user_id', $item)) {
            $item['device_user_id'] = $this->integerOrOriginal($item['device_user_id']);
        }

        if (! array_key_exists('punched_at', $item) && array_key_exists('timestamp', $item) && is_string($item['timestamp'])) {
            $item['punched_at'] = $item['timestamp'];
        }

        if (! array_key_exists('verify_mode', $item) && array_key_exists('type', $item)) {
            $item['verify_mode'] = $this->integerOrOriginal($item['type']);
        }

        if (array_key_exists('state', $item)) {
            $item['state'] = $this->integerOrOriginal($item['state']);
        }

        return $item;
    }

    private function integerOrOriginal(mixed $value): mixed
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        return $value;
    }

    private function optionalByte(mixed $value): ?int
    {
        if (! is_int($value) || $value < 0 || $value > 255) {
            return null;
        }

        return $value;
    }
}
