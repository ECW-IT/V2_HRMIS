<?php

namespace Modules\Attendance\Services;

use App\Support\Effective\EffectiveDating;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Attendance\Enums\AttendanceMode;
use Modules\Attendance\Enums\OtMode;
use Modules\Attendance\Exceptions\InvalidTimeProfile;
use Modules\Attendance\Models\EmploymentTimeProfile;
use Modules\Attendance\Models\Shift;
use Modules\Calendar\Services\PayPeriodService;
use Modules\CoreHr\Enums\EmploymentStatus;
use Modules\CoreHr\Models\Employment;
use Modules\Organisation\Models\EmployeeCategory;

final class TimeProfileService
{
    /** @var list<string> */
    private const TIME_FIELDS = [
        'start_time',
        'end_time',
        'late_from',
        'morning_cutoff',
        'half_day_from',
        'early_leave_from',
    ];

    /**
     * $data: effective_from, shift_id, attendance_mode?, ot_mode?, late_deduction?, break_available?.
     * Optional times (six clock fields) and breaks. Null times clears a custom copy. Omitted times copy the profile in force on that date.
     */
    public function set(Employment $e, array $data): EmploymentTimeProfile
    {
        if ($e->status === EmploymentStatus::EXITED) {
            throw new InvalidTimeProfile('Cannot set a time profile on an exited employment.');
        }

        $from = Carbon::parse($data['effective_from'])->toDateString();
        $hire = Carbon::parse($e->hire_date)->toDateString();
        if ($from < $hire) {
            throw new InvalidTimeProfile('A time profile cannot start before the hire date.');
        }

        if (app(PayPeriodService::class)->isDateLocked((int) $e->company_id, $from)) {
            throw new InvalidTimeProfile('That period is closed.');
        }

        $shift = Shift::query()->findOrFail((int) $data['shift_id']);
        if (! $shift->is_active) {
            throw new InvalidTimeProfile('The shift is not active.');
        }

        $previous = $this->on($e, $from);
        $previous?->load('breaks');

        $attendanceMode = array_key_exists('attendance_mode', $data)
            ? AttendanceMode::from(is_string($data['attendance_mode']) ? $data['attendance_mode'] : $data['attendance_mode']->value)
            : ($previous?->attendance_mode ?? AttendanceMode::PUNCH);

        $otMode = array_key_exists('ot_mode', $data)
            ? $this->optionalOtMode($data['ot_mode'])
            : ($previous !== null ? $previous->ot_mode : OtMode::NONE);

        $lateDeduction = array_key_exists('late_deduction', $data)
            ? $this->optionalBool($data['late_deduction'])
            : ($previous !== null ? $previous->late_deduction : false);

        $breakAvailable = array_key_exists('break_available', $data)
            ? $this->optionalBool($data['break_available'])
            : ($previous !== null ? $previous->break_available : null);

        if ($attendanceMode === AttendanceMode::NO_PUNCH) {
            $otMode = OtMode::NONE;
            $lateDeduction = false;
        }

        $snapshot = $this->snapshot($data, $previous);

        $saved = DB::transaction(function () use ($e, $from, $shift, $attendanceMode, $otMode, $lateDeduction, $breakAvailable, $snapshot) {
            $starting = EmploymentTimeProfile::query()
                ->where('employment_id', $e->id)
                ->whereDate('effective_from', $from)
                ->first();

            $attributes = array_merge([
                'shift_id' => $shift->id,
                'attendance_mode' => $attendanceMode,
                'ot_mode' => $otMode,
                'late_deduction' => $lateDeduction,
                'break_available' => $breakAvailable,
            ], $snapshot['attributes']);

            if ($starting !== null) {
                $starting->fill($attributes);
                $starting->save();
                $profile = $starting;
            } else {
                $profile = new EmploymentTimeProfile(array_merge([
                    'employment_id' => $e->id,
                    'effective_from' => $from,
                    'effective_to' => null,
                ], $attributes));

                /** @var EmploymentTimeProfile $profile */
                $profile = EffectiveDating::insert($profile);
            }

            $this->replaceBreaks($profile, $snapshot['breaks']);

            return $profile;
        });

        if ($previous !== null) {
            $today = Carbon::now(config('app.timezone'))->toDateString();
            if ($from <= $today) {
                app(TimecardService::class)->computeRange((int) $e->company_id, $from, $today, [$e->id]);
            }
        }

        return $saved->fresh(['shift', 'breaks']);
    }

    public function on(Employment|int $employment, \DateTimeInterface|string $date): ?EmploymentTimeProfile
    {
        $employmentId = $employment instanceof Employment ? $employment->id : $employment;

        return EmploymentTimeProfile::query()
            ->where('employment_id', $employmentId)
            ->effectiveOn($date)
            ->first();
    }

    /** Same $data applied to many employments. Returns [employment_id => EmploymentTimeProfile|string error]. */
    public function setMany(array $employmentIds, array $data): array
    {
        $results = [];

        foreach ($employmentIds as $employmentId) {
            try {
                $employment = Employment::query()->findOrFail((int) $employmentId);
                $results[(int) $employmentId] = $this->set($employment, $data);
            } catch (\Throwable $e) {
                $results[(int) $employmentId] = $e->getMessage();
            }
        }

        return $results;
    }

    /**
     * Employee value wins. Null OT or late uses the category.
     * Custom clock times and breaks on the profile win over the shared shift and the category.
     *
     * @return array{shift: Shift, ot_mode: OtMode, late_deduction: bool, breaks: list<array{start: string, end: string, paid: bool}>}
     */
    public function resolve(Employment $employment, EmploymentTimeProfile $profile): array
    {
        $employment->loadMissing('employeeCategory.breaks');
        $profile->loadMissing(['shift.breaks', 'breaks']);
        $category = $employment->employeeCategory;

        return [
            'shift' => $this->resolvedShift($profile),
            'ot_mode' => $profile->ot_mode ?? $this->categoryOtMode($category),
            'late_deduction' => $profile->late_deduction ?? (bool) ($category?->late_deduction ?? false),
            'breaks' => $this->resolvedBreaks($profile, $category),
        ];
    }

    private function resolvedShift(EmploymentTimeProfile $profile): Shift
    {
        if (! $profile->uses_custom_times) {
            return $profile->shift;
        }

        return new Shift([
            'start_time' => $profile->start_time,
            'end_time' => $profile->end_time,
            'late_from' => $profile->late_from,
            'morning_cutoff' => $profile->morning_cutoff,
            'half_day_from' => $profile->half_day_from,
            'early_leave_from' => $profile->early_leave_from,
        ]);
    }

    /**
     * @return array{attributes: array<string, mixed>, breaks: list<array{name: string, start_time: string, end_time: string, is_paid: bool}>}
     */
    private function snapshot(array $data, ?EmploymentTimeProfile $previous): array
    {
        if (array_key_exists('times', $data) && $data['times'] === null) {
            return [
                'attributes' => $this->blankTimes(),
                'breaks' => [],
            ];
        }

        if (array_key_exists('times', $data)) {
            $times = $this->validatedTimes($data['times']);
            $breaks = $this->validatedBreaks($data['breaks'] ?? []);

            return [
                'attributes' => array_merge(['uses_custom_times' => true], $times),
                'breaks' => $breaks,
            ];
        }

        if ($previous !== null && $previous->uses_custom_times) {
            $times = [];
            foreach (self::TIME_FIELDS as $field) {
                $times[$field] = $this->normalizeTime($previous->getAttribute($field));
            }

            return [
                'attributes' => array_merge(['uses_custom_times' => true], $times),
                'breaks' => $previous->breaks->map(fn ($break) => [
                    'name' => $break->name,
                    'start_time' => (string) $break->start_time,
                    'end_time' => (string) $break->end_time,
                    'is_paid' => (bool) $break->is_paid,
                ])->all(),
            ];
        }

        return [
            'attributes' => $this->blankTimes(),
            'breaks' => [],
        ];
    }

    /** @return array<string, mixed> */
    private function blankTimes(): array
    {
        $attributes = ['uses_custom_times' => false];
        foreach (self::TIME_FIELDS as $field) {
            $attributes[$field] = null;
        }

        return $attributes;
    }

    /**
     * @return array<string, string>
     */
    private function validatedTimes(mixed $times): array
    {
        if (! is_array($times)) {
            throw new InvalidTimeProfile('Enter all six shift times.');
        }

        $normalized = [];
        foreach (self::TIME_FIELDS as $field) {
            if (! isset($times[$field]) || $times[$field] === '') {
                throw new InvalidTimeProfile('Enter all six shift times.');
            }
            $normalized[$field] = $this->normalizeTime($times[$field]);
        }

        $start = $this->seconds($normalized['start_time']);
        $late = $this->seconds($normalized['late_from']);
        $morning = $this->seconds($normalized['morning_cutoff']);
        $half = $this->seconds($normalized['half_day_from']);
        $early = $this->seconds($normalized['early_leave_from']);
        $end = $this->seconds($normalized['end_time']);

        if (! ($start < $late && $late <= $morning && $morning < $half && $half < $early && $early < $end)) {
            throw new InvalidTimeProfile('Times must be in order: start, late from, morning cut-off, half day from, early leave from, then end.');
        }

        return $normalized;
    }

    /**
     * @param  list<array<string, mixed>>  $breaks
     * @return list<array{name: string, start_time: string, end_time: string, is_paid: bool}>
     */
    private function validatedBreaks(array $breaks): array
    {
        $clean = [];

        foreach ($breaks as $break) {
            $name = trim((string) ($break['name'] ?? ''));
            if ($name === '') {
                throw new InvalidTimeProfile('Each break needs a name.');
            }
            if ($this->seconds($break['start_time'] ?? '') >= $this->seconds($break['end_time'] ?? '')) {
                throw new InvalidTimeProfile('A break end time must be after its start time.');
            }

            $clean[] = [
                'name' => $name,
                'start_time' => $this->normalizeTime($break['start_time']),
                'end_time' => $this->normalizeTime($break['end_time']),
                'is_paid' => (bool) ($break['is_paid'] ?? false),
            ];
        }

        return $clean;
    }

    /**
     * @param  list<array{name: string, start_time: string, end_time: string, is_paid: bool}>  $breaks
     */
    private function replaceBreaks(EmploymentTimeProfile $profile, array $breaks): void
    {
        $profile->breaks()->delete();

        foreach ($breaks as $break) {
            $profile->breaks()->create($break);
        }
    }

    private function optionalOtMode(mixed $value): ?OtMode
    {
        if ($value === null || $value === '' || $value === 'DEFAULT') {
            return null;
        }

        return OtMode::from(is_string($value) ? $value : $value->value);
    }

    private function optionalBool(mixed $value): ?bool
    {
        if ($value === null || $value === '' || $value === 'DEFAULT') {
            return null;
        }

        if ($value === true || $value === 1 || $value === '1' || $value === 'YES') {
            return true;
        }

        return false;
    }

    private function categoryOtMode(?EmployeeCategory $category): OtMode
    {
        if ($category === null) {
            return OtMode::NONE;
        }

        $early = (bool) $category->early_ot;
        $evening = (bool) $category->evening_ot;

        return match (true) {
            $early && $evening => OtMode::BOTH,
            $early => OtMode::MORNING,
            $evening => OtMode::EVENING,
            default => OtMode::NONE,
        };
    }

    /**
     * @return list<array{start: string, end: string, paid: bool}>
     */
    private function resolvedBreaks(EmploymentTimeProfile $profile, ?EmployeeCategory $category): array
    {
        if ($profile->break_available === false) {
            return [];
        }

        if ($profile->uses_custom_times) {
            return $profile->breaks->map(fn ($break) => [
                'start' => (string) $break->start_time,
                'end' => (string) $break->end_time,
                'paid' => (bool) $break->is_paid,
            ])->all();
        }

        if ($category !== null && $category->breaks->isNotEmpty()) {
            return $category->breaks->map(fn ($break) => [
                'start' => (string) $break->start_time,
                'end' => (string) $break->end_time,
                'paid' => (bool) $break->is_paid,
            ])->all();
        }

        return $profile->shift->breaks->map(fn ($break) => [
            'start' => (string) $break->start_time,
            'end' => (string) $break->end_time,
            'paid' => (bool) $break->is_paid,
        ])->all();
    }

    private function normalizeTime(mixed $value): string
    {
        return Carbon::parse((string) $value)->format('H:i:s');
    }

    private function seconds(mixed $value): int
    {
        $carbon = Carbon::parse((string) $value);

        return ($carbon->hour * 3600) + ($carbon->minute * 60) + $carbon->second;
    }
}
