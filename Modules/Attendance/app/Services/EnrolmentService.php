<?php

namespace Modules\Attendance\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Attendance\Exceptions\DeviceUserIdInUse;
use Modules\Attendance\Models\BiometricEnrolment;
use Modules\CoreHr\Models\Employment;

final class EnrolmentService
{
    public function enrol(Employment $e, string $deviceGroup, int $deviceUserId, \DateTimeInterface|string $from): BiometricEnrolment
    {
        if ($deviceUserId < 1) {
            throw new \InvalidArgumentException('Device user ID must be 1 or more.');
        }

        $fromDate = $this->dateString($from);
        if ($fromDate < $this->dateString($e->hire_date)) {
            throw new \InvalidArgumentException('Enrolment date is before the hire date.');
        }

        return DB::transaction(function () use ($e, $deviceGroup, $deviceUserId, $fromDate) {
            $openSameGroup = BiometricEnrolment::query()
                ->where('employment_id', $e->id)
                ->where('device_group', $deviceGroup)
                ->whereNull('valid_to')
                ->lockForUpdate()
                ->first();

            if ($openSameGroup !== null) {
                $closeOn = CarbonImmutable::createFromFormat('Y-m-d', $fromDate)->subDay()->toDateString();
                $openSameGroup->update(['valid_to' => $closeOn]);
            }

            $this->assertAvailable($deviceGroup, $deviceUserId, $fromDate, lock: true);

            $enrolment = BiometricEnrolment::query()->create([
                'employment_id' => $e->id,
                'device_group' => $deviceGroup,
                'device_user_id' => $deviceUserId,
                'valid_from' => $fromDate,
                'valid_to' => null,
            ]);

            app(PunchResolver::class)->resolvePending($deviceGroup);

            return $enrolment;
        });
    }

    /** Rejects when an overlapping enrolment already holds the same device user ID in the group. */
    public function assertAvailable(string $deviceGroup, int $deviceUserId, \DateTimeInterface|string $from, bool $lock = false): void
    {
        $fromDate = $this->dateString($from);

        $query = BiometricEnrolment::query()
            ->where('device_group', $deviceGroup)
            ->where('device_user_id', $deviceUserId);

        if ($lock) {
            $query->lockForUpdate();
        }

        foreach ($query->get() as $existing) {
            if ($this->rangesOverlap($existing->valid_from->toDateString(), $existing->valid_to?->toDateString(), $fromDate, null)) {
                $holder = Employment::query()->find($existing->employment_id);
                $number = $holder?->employee_no ?? (string) $existing->employment_id;
                throw new DeviceUserIdInUse("Device user ID is already held by {$number}.");
            }
        }
    }

    public function end(BiometricEnrolment $enrolment, \DateTimeInterface|string $on): BiometricEnrolment
    {
        $onDate = $this->dateString($on);
        if ($onDate < $this->dateString($enrolment->valid_from)) {
            throw new \InvalidArgumentException('End date cannot be before the start date.');
        }

        $enrolment->update(['valid_to' => $onDate]);

        return $enrolment->fresh();
    }

    /** Closes every open enrolment of the employment on $on. Returns rows closed. */
    public function endAllFor(Employment $e, \DateTimeInterface|string $on): int
    {
        $onDate = $this->dateString($on);

        return BiometricEnrolment::query()
            ->where('employment_id', $e->id)
            ->whereNull('valid_to')
            ->update(['valid_to' => $onDate]);
    }

    /** employment_id holding the id at that moment (date part of $at, inclusive range), or null. */
    public function resolve(string $deviceGroup, int $deviceUserId, \DateTimeInterface|string $at): ?int
    {
        $key = $deviceUserId.'|'.$this->dateString($at);

        return $this->resolveMany($deviceGroup, [[$deviceUserId, $at]])[$key] ?? null;
    }

    /**
     * Same rules as resolve(), in two queries for a whole batch.
     *
     * @param  list<array{0: int, 1: \DateTimeInterface|string}>  $lookups
     * @return array<string, int|null> keyed by "{device_user_id}|{Y-m-d}"
     */
    public function resolveMany(string $deviceGroup, array $lookups): array
    {
        $results = [];
        $uids = [];

        foreach ($lookups as $lookup) {
            $uid = (int) $lookup[0];
            $day = $this->dateString($lookup[1]);
            $key = $uid.'|'.$day;
            if (! array_key_exists($key, $results)) {
                $results[$key] = null;
                $uids[$uid] = true;
            }
        }

        if ($uids === []) {
            return $results;
        }

        $enrolments = BiometricEnrolment::query()
            ->where('device_group', $deviceGroup)
            ->whereIn('device_user_id', array_keys($uids))
            ->get(['employment_id', 'device_user_id', 'valid_from', 'valid_to']);

        $byUid = [];
        foreach ($enrolments as $enrolment) {
            $byUid[(int) $enrolment->device_user_id][] = $enrolment;
        }

        $employmentIds = $enrolments->pluck('employment_id')->unique()->values()->all();
        $activeIds = [];
        if ($employmentIds !== []) {
            $activeIds = Employment::query()
                ->whereIn('id', $employmentIds)
                ->where('attendance_active', true)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->flip()
                ->all();
        }

        foreach ($results as $key => $_) {
            [$uid, $day] = explode('|', $key, 2);
            $uid = (int) $uid;

            foreach ($byUid[$uid] ?? [] as $enrolment) {
                $from = $enrolment->valid_from->toDateString();
                $to = $enrolment->valid_to?->toDateString();
                if ($from <= $day && ($to === null || $to >= $day)) {
                    $employmentId = (int) $enrolment->employment_id;
                    if (isset($activeIds[$employmentId])) {
                        $results[$key] = $employmentId;
                    }
                    break;
                }
            }
        }

        return $results;
    }

    private function rangesOverlap(string $aFrom, ?string $aTo, string $bFrom, ?string $bTo): bool
    {
        $aEnd = $aTo ?? '9999-12-31';
        $bEnd = $bTo ?? '9999-12-31';

        return $aFrom <= $bEnd && $bFrom <= $aEnd;
    }

    private function dateString(\DateTimeInterface|string $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $value, $m) === 1) {
            return $m[0];
        }

        return (string) $value;
    }
}
