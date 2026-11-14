<?php

namespace Modules\Attendance\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Attendance\Exceptions\InvalidShift;
use Modules\Attendance\Exceptions\TimecardLocked;
use Modules\Attendance\Models\EmploymentTimeProfile;
use Modules\Attendance\Models\Shift;
use Modules\Attendance\Models\ShiftBreak;
use Modules\Attendance\Models\Timecard;
use Modules\Calendar\Enums\PeriodStatus;
use Modules\CoreHr\Enums\EmploymentStatus;

final class ShiftService
{
    public function create(array $data, array $breaks = []): Shift
    {
        $this->assertTimeOrder($data);
        $this->assertBreaks($breaks);

        return DB::transaction(function () use ($data, $breaks) {
            $shift = Shift::query()->create([
                'code' => $data['code'],
                'name' => $data['name'],
                'start_time' => $this->normalizeTime($data['start_time']),
                'end_time' => $this->normalizeTime($data['end_time']),
                'late_from' => $this->normalizeTime($data['late_from']),
                'morning_cutoff' => $this->normalizeTime($data['morning_cutoff']),
                'half_day_from' => $this->normalizeTime($data['half_day_from']),
                'early_leave_from' => $this->normalizeTime($data['early_leave_from']),
                'is_active' => $data['is_active'] ?? true,
            ]);

            $this->syncBreaks($shift, $breaks);

            return $shift->fresh(['breaks']);
        });
    }

    public function update(Shift $shift, array $data, ?array $breaks = null): Shift
    {
        $merged = array_merge([
            'start_time' => $shift->start_time,
            'end_time' => $shift->end_time,
            'late_from' => $shift->late_from,
            'morning_cutoff' => $shift->morning_cutoff,
            'half_day_from' => $shift->half_day_from,
            'early_leave_from' => $shift->early_leave_from,
        ], $data);

        $this->assertTimeOrder($merged);
        if ($breaks !== null) {
            $this->assertBreaks($breaks);
        }

        return DB::transaction(function () use ($shift, $data, $breaks) {
            $payload = [];
            foreach (['code', 'name', 'start_time', 'end_time', 'late_from', 'morning_cutoff', 'half_day_from', 'early_leave_from', 'is_active'] as $key) {
                if (array_key_exists($key, $data)) {
                    $payload[$key] = in_array($key, ['start_time', 'end_time', 'late_from', 'morning_cutoff', 'half_day_from', 'early_leave_from'], true)
                        ? $this->normalizeTime($data[$key])
                        : $data[$key];
                }
            }

            if ($payload !== []) {
                $shift->update($payload);
            }

            if ($breaks !== null) {
                $shift->breaks()->delete();
                $this->syncBreaks($shift, $breaks);
            }

            $fresh = $shift->fresh(['breaks']);

            $timecards = Timecard::withoutGlobalScopes()
                ->where('shift_id', $shift->id)
                ->whereHas('payPeriod', fn ($q) => $q->withoutGlobalScopes()->where('status', PeriodStatus::OPEN))
                ->get();

            $svc = app(TimecardService::class);
            foreach ($timecards as $timecard) {
                try {
                    $svc->compute((int) $timecard->employment_id, $timecard->work_date->toDateString());
                } catch (TimecardLocked) {
                    // skip
                }
            }

            return $fresh;
        });
    }

    public function deactivate(Shift $shift): void
    {
        $inUse = EmploymentTimeProfile::query()
            ->where('shift_id', $shift->id)
            ->current()
            ->whereHas('employment', fn ($q) => $q->where('status', '!=', EmploymentStatus::EXITED))
            ->exists();

        if ($inUse) {
            throw new InvalidShift('This shift is in use by a current time profile and cannot be deactivated.');
        }

        $shift->update(['is_active' => false]);
    }

    private function assertTimeOrder(array $data): void
    {
        $start = $this->seconds($data['start_time']);
        $late = $this->seconds($data['late_from']);
        $morning = $this->seconds($data['morning_cutoff']);
        $half = $this->seconds($data['half_day_from']);
        $early = $this->seconds($data['early_leave_from']);
        $end = $this->seconds($data['end_time']);

        if (! ($start < $late && $late <= $morning && $morning < $half && $half < $early && $early < $end)) {
            throw new InvalidShift('Shift times must be in order: start < late from ≤ morning cut-off < half day from < early leave from < end.');
        }
    }

    /** @param list<array{name: string, start_time: string, end_time: string, is_paid?: bool}> $breaks */
    private function assertBreaks(array $breaks): void
    {
        foreach ($breaks as $break) {
            if ($this->seconds($break['start_time']) >= $this->seconds($break['end_time'])) {
                throw new InvalidShift('A break end time must be after its start time.');
            }
        }
    }

    /** @param list<array{name: string, start_time: string, end_time: string, is_paid?: bool}> $breaks */
    private function syncBreaks(Shift $shift, array $breaks): void
    {
        foreach ($breaks as $break) {
            ShiftBreak::query()->create([
                'shift_id' => $shift->id,
                'name' => $break['name'],
                'start_time' => $this->normalizeTime($break['start_time']),
                'end_time' => $this->normalizeTime($break['end_time']),
                'is_paid' => (bool) ($break['is_paid'] ?? false),
            ]);
        }
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
