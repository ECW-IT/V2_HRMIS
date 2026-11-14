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

            $candidates = BiometricEnrolment::query()
                ->where('device_group', $deviceGroup)
                ->where('device_user_id', $deviceUserId)
                ->lockForUpdate()
                ->get();

            foreach ($candidates as $existing) {
                if ($this->rangesOverlap($existing->valid_from->toDateString(), $existing->valid_to?->toDateString(), $fromDate, null)) {
                    $holder = Employment::query()->find($existing->employment_id);
                    $number = $holder?->employee_no ?? (string) $existing->employment_id;
                    throw new DeviceUserIdInUse("Device user ID is already held by {$number}.");
                }
            }

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
        $day = $this->dateString($at);

        $enrolment = BiometricEnrolment::query()
            ->where('device_group', $deviceGroup)
            ->where('device_user_id', $deviceUserId)
            ->whereDate('valid_from', '<=', $day)
            ->where(function ($q) use ($day) {
                $q->whereNull('valid_to')
                    ->orWhereDate('valid_to', '>=', $day);
            })
            ->first();

        return $enrolment?->employment_id;
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
