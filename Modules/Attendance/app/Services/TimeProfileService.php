<?php

namespace Modules\Attendance\Services;

use App\Support\Effective\EffectiveDating;
use Carbon\Carbon;
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
    /** $data: effective_from, shift_id, attendance_mode?, ot_mode?, late_deduction? — missing keys copy the profile effective on that date, or default to PUNCH / NONE / false. */
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

        $starting = EmploymentTimeProfile::query()
            ->where('employment_id', $e->id)
            ->whereDate('effective_from', $from)
            ->first();

        if ($starting !== null) {
            $starting->fill([
                'shift_id' => $shift->id,
                'attendance_mode' => $attendanceMode,
                'ot_mode' => $otMode,
                'late_deduction' => $lateDeduction,
                'break_available' => $breakAvailable,
            ]);
            $starting->save();
            $saved = $starting;
        } else {
            $profile = new EmploymentTimeProfile([
                'employment_id' => $e->id,
                'effective_from' => $from,
                'effective_to' => null,
                'shift_id' => $shift->id,
                'attendance_mode' => $attendanceMode,
                'ot_mode' => $otMode,
                'late_deduction' => $lateDeduction,
                'break_available' => $breakAvailable,
            ]);

            /** @var EmploymentTimeProfile $saved */
            $saved = EffectiveDating::insert($profile);
        }

        // Recompute open dates when changing an existing profile (not the first assignment).
        if ($previous !== null) {
            $today = Carbon::now(config('app.timezone'))->toDateString();
            if ($from <= $today) {
                app(TimecardService::class)->computeRange((int) $e->company_id, $from, $today, [$e->id]);
            }
        }

        return $saved->fresh(['shift']);
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
     * Employee value wins. Null OT or late uses the category. Break available No sends no breaks.
     *
     * @return array{ot_mode: OtMode, late_deduction: bool, breaks: list<array{start: string, end: string, paid: bool}>}
     */
    public function resolve(Employment $employment, EmploymentTimeProfile $profile): array
    {
        $employment->loadMissing('employeeCategory.breaks');
        $profile->loadMissing('shift.breaks');
        $category = $employment->employeeCategory;

        return [
            'ot_mode' => $profile->ot_mode ?? $this->categoryOtMode($category),
            'late_deduction' => $profile->late_deduction ?? (bool) ($category?->late_deduction ?? false),
            'breaks' => $this->resolvedBreaks($profile, $category),
        ];
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
}
