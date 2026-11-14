<?php

namespace Modules\Attendance\Services;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Modules\Attendance\Enums\AdjustmentType;
use Modules\Attendance\Enums\AttendanceMode;
use Modules\Attendance\Enums\DayType;
use Modules\Attendance\Enums\DecisionLogKind;
use Modules\Attendance\Enums\PunchResolution;
use Modules\Attendance\Enums\TimecardException;
use Modules\Attendance\Exceptions\TimecardLocked;
use Modules\Attendance\Models\Punch;
use Modules\Attendance\Models\Timecard;
use Modules\Attendance\Models\TimecardDecisionLog;
use Modules\Attendance\Timecards\DayCalculator;
use Modules\Attendance\Timecards\WorkDate;
use Modules\Calendar\Enums\PeriodStatus;
use Modules\Calendar\Services\HolidayService;
use Modules\Calendar\Services\PayPeriodService;
use Modules\CoreHr\Models\Employment;
use Modules\Organisation\Models\Company;

final class TimecardService
{
    public function __construct(
        private readonly DayCalculator $calculator,
        private readonly TimeProfileService $profiles,
        private readonly PayPeriodService $periods,
        private readonly HolidayService $holidays,
    ) {}

    /** @return list<CarbonImmutable> */
    public function effectivePunches(int $employmentId, string $workDate): array
    {
        [$from, $to] = WorkDate::window($workDate);

        $adjustments = app(AdjustmentService::class)->approvedFor($employmentId, $workDate);
        $voidedPunchIds = $adjustments
            ->where('type', AdjustmentType::VOID_PUNCH)
            ->pluck('punch_id')
            ->filter()
            ->all();

        $times = Punch::query()
            ->where('employment_id', $employmentId)
            ->where('resolution', PunchResolution::RESOLVED)
            ->where('punched_at', '>=', $from->format('Y-m-d H:i:s'))
            ->where('punched_at', '<', $to->format('Y-m-d H:i:s'))
            ->when($voidedPunchIds !== [], fn ($q) => $q->whereNotIn('id', $voidedPunchIds))
            ->orderBy('punched_at')
            ->get()
            ->map(fn (Punch $p) => CarbonImmutable::parse($p->punched_at, config('app.timezone')))
            ->all();

        foreach ($adjustments->where('type', AdjustmentType::ADD_PUNCH) as $add) {
            if ($add->punched_at !== null) {
                $times[] = CarbonImmutable::parse($add->punched_at, config('app.timezone'));
            }
        }

        usort($times, fn (CarbonImmutable $a, CarbonImmutable $b) => $a <=> $b);

        return array_values($times);
    }

    public function isLocked(int $companyId, \DateTimeInterface|string $workDate): bool
    {
        $day = Carbon::parse($workDate, config('app.timezone'));
        $company = Company::query()->findOrFail($companyId);
        $period = $this->periods->ensure($company, (int) $day->year, (int) $day->month);

        return $period->status === PeriodStatus::CLOSED;
    }

    public function compute(Employment|int $employment, \DateTimeInterface|string $workDate): ?Timecard
    {
        $employment = $employment instanceof Employment
            ? $employment
            : Employment::query()->findOrFail($employment);

        $day = Carbon::parse($workDate, config('app.timezone'))->toDateString();
        $company = Company::query()->findOrFail($employment->company_id);
        $period = $this->periods->ensure($company, (int) Carbon::parse($day)->year, (int) Carbon::parse($day)->month);

        if ($period->status === PeriodStatus::CLOSED) {
            throw new TimecardLocked('This pay period is closed.');
        }

        $today = Carbon::now(config('app.timezone'))->toDateString();
        $hire = Carbon::parse($employment->hire_date)->toDateString();
        $exit = $employment->exit_date !== null
            ? Carbon::parse($employment->exit_date)->toDateString()
            : null;

        if ($day < $hire || ($exit !== null && $day > $exit) || $day > $today) {
            $this->deleteUnlockedRow((int) $employment->id, $day);

            return null;
        }

        $punches = $this->effectivePunches((int) $employment->id, $day);
        $profile = $this->profiles->on($employment, $day);
        $profile?->loadMissing(['shift.breaks']);

        if ($profile === null && $punches === []) {
            $this->deleteUnlockedRow((int) $employment->id, $day);

            return null;
        }

        if ($profile !== null && $profile->attendance_mode === AttendanceMode::NO_PUNCH) {
            $this->deleteUnlockedRow((int) $employment->id, $day);

            return null;
        }

        $isHoliday = $this->holidays->on((int) $employment->company_id, $day) !== null;
        $existing = Timecard::withoutGlobalScopes()
            ->where('employment_id', $employment->id)
            ->whereDate('work_date', $day)
            ->first();

        if ($profile === null) {
            $first = $punches[0] ?? null;
            $last = count($punches) > 1 ? $punches[count($punches) - 1] : null;
            $engine = [
                'company_id' => $employment->company_id,
                'employment_id' => $employment->id,
                'work_date' => $day,
                'pay_period_id' => $period->id,
                'shift_id' => null,
                'first_in' => $first?->format('Y-m-d H:i:s'),
                'last_out' => $last?->format('Y-m-d H:i:s'),
                'punch_count' => count($punches),
                'day_type' => DayType::NONE,
                'day_value' => 0,
                'short_leave' => false,
                'late_in_minutes' => 0,
                'early_out_minutes' => 0,
                'ot_morning_minutes' => 0,
                'ot_evening_minutes' => 0,
                'is_holiday' => $isHoliday,
                'exception' => TimecardException::NO_PROFILE,
                'computed_at' => now(),
            ];
        } else {
            $shift = $profile->shift;
            $breaks = $shift->breaks->map(fn ($b) => [
                'start' => (string) $b->start_time,
                'end' => (string) $b->end_time,
                'paid' => (bool) $b->is_paid,
            ])->all();

            $result = $this->calculator->calculate(
                $day,
                $shift,
                $breaks,
                $profile->ot_mode,
                (bool) $profile->late_deduction,
                $punches,
            );

            $engine = [
                'company_id' => $employment->company_id,
                'employment_id' => $employment->id,
                'work_date' => $day,
                'pay_period_id' => $period->id,
                'shift_id' => $shift->id,
                'first_in' => $result->firstIn?->format('Y-m-d H:i:s'),
                'last_out' => $result->lastOut?->format('Y-m-d H:i:s'),
                'punch_count' => $result->punchCount,
                'day_type' => $result->dayType,
                'day_value' => $result->dayValue,
                'short_leave' => $result->shortLeave,
                'late_in_minutes' => $result->lateInMinutes,
                'early_out_minutes' => $result->earlyOutMinutes,
                'ot_morning_minutes' => $result->otMorningMinutes,
                'ot_evening_minutes' => $result->otEveningMinutes,
                'is_holiday' => $isHoliday,
                'exception' => $result->exception,
                'computed_at' => now(),
            ];
        }

        if ($existing !== null) {
            $oldOt = (int) $existing->ot_morning_minutes + (int) $existing->ot_evening_minutes;
            $newOt = (int) $engine['ot_morning_minutes'] + (int) $engine['ot_evening_minutes'];
            $previousApproved = $existing->ot_approved_minutes;
            $existing->fill($engine);
            if ($oldOt !== $newOt) {
                $existing->ot_approved_minutes = null;
                $existing->ot_decided_by = null;
                $existing->ot_decided_at = null;
            }
            $existing->save();

            if ($oldOt !== $newOt && $previousApproved !== null) {
                TimecardDecisionLog::query()->create([
                    'timecard_id' => $existing->id,
                    'employment_id' => $existing->employment_id,
                    'work_date' => $day,
                    'kind' => DecisionLogKind::OT_CLEARED->value,
                    'old_value' => $previousApproved,
                    'new_value' => null,
                    'reason' => 'Computed OT changed',
                    'user_id' => null,
                    'created_at' => now(),
                ]);
            }

            return $existing->fresh(['payPeriod']);
        }

        $row = Timecard::withoutGlobalScopes()->create($engine);

        return $row->fresh(['payPeriod']);
    }

    public function computeRange(int $companyId, \DateTimeInterface|string $from, \DateTimeInterface|string $to, ?array $employmentIds = null): int
    {
        $fromDate = Carbon::parse($from, config('app.timezone'))->startOfDay();
        $toDate = Carbon::parse($to, config('app.timezone'))->startOfDay();
        $today = Carbon::now(config('app.timezone'))->startOfDay();
        if ($toDate->gt($today)) {
            $toDate = $today->copy();
        }

        $written = 0;
        $cursor = $fromDate->copy();

        while ($cursor->lte($toDate)) {
            $day = $cursor->toDateString();
            $employments = $this->punchEmploymentsFor($companyId, $day, $employmentIds);

            foreach ($employments as $employment) {
                try {
                    $row = $this->compute($employment, $day);
                    if ($row !== null) {
                        $written++;
                    }
                } catch (TimecardLocked) {
                    // skip closed periods
                } catch (\Throwable $e) {
                    Log::error('Timecard compute failed', [
                        'employment_id' => $employment->id,
                        'work_date' => $day,
                        'message' => $e->getMessage(),
                    ]);
                }
            }

            $cursor->addDay();
        }

        return $written;
    }

    /** @param  list<int>|null  $employmentIds */
    private function punchEmploymentsFor(int $companyId, string $day, ?array $employmentIds): Collection
    {
        $query = Employment::query()
            ->where('company_id', $companyId)
            ->whereDate('hire_date', '<=', $day)
            ->where(function ($q) use ($day) {
                $q->whereNull('exit_date')->orWhereDate('exit_date', '>=', $day);
            });

        if ($employmentIds !== null) {
            $query->whereIn('id', $employmentIds);
        }

        return $query->orderBy('id')->get()->filter(function (Employment $e) use ($day) {
            $profile = $this->profiles->on($e, $day);
            if ($profile === null) {
                return false;
            }

            return $profile->attendance_mode === AttendanceMode::PUNCH;
        })->values();
    }

    private function deleteUnlockedRow(int $employmentId, string $day): void
    {
        Timecard::withoutGlobalScopes()
            ->where('employment_id', $employmentId)
            ->whereDate('work_date', $day)
            ->delete();
    }
}
