<?php

namespace Modules\Attendance\Services;

use Carbon\CarbonImmutable;
use Modules\Attendance\Exceptions\InvalidPunch;
use Modules\Attendance\Exceptions\TimecardLocked;
use Modules\Attendance\Models\BiometricEnrolment;
use Modules\Attendance\Models\Device;
use Modules\Attendance\Models\Punch;
use Modules\Attendance\Timecards\WorkDate;
use Modules\Calendar\Services\HolidayService;
use Modules\CoreHr\Models\Assignment;
use Modules\CoreHr\Models\Employment;
use Modules\Organisation\Models\OrgUnit;
use Modules\Organisation\Services\OrgUnitService;

final class MonthPunchService
{
    public function __construct(
        private readonly HolidayService $holidays,
        private readonly OrgUnitService $orgUnits,
        private readonly ManualPunchService $punches,
        private readonly TimecardService $timecards,
    ) {}

    /**
     * @return array{
     *     locked: bool,
     *     rows: list<array{
     *         employment_id: int,
     *         employee_no: string,
     *         name: string,
     *         enrolment_missing: bool,
     *         days: list<array{
     *             date: string,
     *             in: string,
     *             out: string,
     *             in_id: int|null,
     *             out_id: int|null,
     *             in_original: string,
     *             out_original: string,
     *             holiday: string
     *         }>
     *     }>
     * }
     */
    public function load(string $mode, string $month, string $employeeNo, ?int $departmentId): array
    {
        $start = $this->monthStart($month);
        $employments = $mode === 'department'
            ? $this->departmentEmployments($departmentId, $start)
            : $this->oneEmployment($employeeNo);

        if ($employments === []) {
            throw new InvalidPunch($mode === 'department'
                ? 'No employees are assigned to that department for this month.'
                : 'No employee has that number.');
        }

        $companyId = (int) $employments[0]->company_id;
        $end = $start->endOfMonth()->startOfDay();
        $holidayNames = [];
        foreach ($this->holidays->between($companyId, $start, $end) as $date => $holiday) {
            $holidayNames[$date] = $holiday->name;
        }

        $rows = [];
        foreach ($employments as $employment) {
            $rows[] = $this->row($employment, $start, $end, $holidayNames);
        }

        return [
            'locked' => $this->timecards->isLocked($companyId, $start->toDateString()),
            'rows' => $rows,
        ];
    }

    /**
     * @param  list<array{
     *     employment_id: int,
     *     employee_no: string,
     *     name: string,
     *     enrolment_missing: bool,
     *     days: list<array{
     *         date: string,
     *         in: string,
     *         out: string,
     *         in_id: int|null,
     *         out_id: int|null,
     *         in_original: string,
     *         out_original: string,
     *         holiday: string
     *     }>
     * }>  $rows
     */
    public function save(string $month, array $rows): string
    {
        $start = $this->monthStart($month);
        if ($rows === []) {
            throw new InvalidPunch('Load a month before saving.');
        }

        $first = Employment::query()->find($rows[0]['employment_id']);
        if ($first !== null && $this->timecards->isLocked((int) $first->company_id, $start->toDateString())) {
            throw new TimecardLocked('This pay period is closed.');
        }

        $skipped = [];
        $problems = [];

        foreach ($rows as $row) {
            $employment = Employment::query()->find($row['employment_id']);
            if ($employment === null) {
                continue;
            }

            $placed = $this->placement($employment, $start);
            if ($placed === null) {
                $skipped[] = $row['employee_no'].' '.$row['name'];

                continue;
            }

            foreach ($row['days'] as $day) {
                try {
                    $this->applySlot($placed['device'], $placed['device_user_id'], $day['date'], $day['in_id'], $day['in_original'], (string) ($day['in'] ?? ''));
                    $this->applySlot($placed['device'], $placed['device_user_id'], $day['date'], $day['out_id'], $day['out_original'], (string) ($day['out'] ?? ''));
                } catch (InvalidPunch $e) {
                    $problems[] = $row['employee_no'].' '.$day['date'].': '.$e->getMessage();
                }
            }
        }

        $message = 'Month saved.';
        if ($skipped !== []) {
            $message .= ' Skipped (no fingerprint enrolment): '.implode(', ', $skipped).'.';
        }
        if ($problems !== []) {
            $message .= ' '.implode(' ', $problems);
        }

        return $message;
    }

    /**
     * @param  array<string, string>  $holidayNames
     * @return array{
     *     employment_id: int,
     *     employee_no: string,
     *     name: string,
     *     enrolment_missing: bool,
     *     days: list<array{
     *         date: string,
     *         in: string,
     *         out: string,
     *         in_id: int|null,
     *         out_id: int|null,
     *         in_original: string,
     *         out_original: string,
     *         holiday: string
     *     }>
     * }
     */
    private function row(Employment $employment, CarbonImmutable $start, CarbonImmutable $end, array $holidayNames): array
    {
        $windowFrom = WorkDate::window($start->toDateString())[0];
        $windowTo = WorkDate::window($end->toDateString())[1];

        $punches = Punch::query()
            ->where('employment_id', $employment->id)
            ->where('punched_at', '>=', $windowFrom->format('Y-m-d H:i:s'))
            ->where('punched_at', '<', $windowTo->format('Y-m-d H:i:s'))
            ->orderBy('punched_at')
            ->get();

        $byDate = [];
        foreach ($punches as $punch) {
            $byDate[WorkDate::of($punch->punched_at)][] = $punch;
        }

        $days = [];
        $cursor = $start;
        while ($cursor->lte($end)) {
            $date = $cursor->toDateString();
            $dayPunches = $byDate[$date] ?? [];
            $in = $dayPunches[0] ?? null;
            $out = count($dayPunches) > 1 ? $dayPunches[count($dayPunches) - 1] : null;
            $inTime = $this->clock($in);
            $outTime = $this->clock($out);

            $days[] = [
                'date' => $date,
                'in' => $inTime,
                'out' => $outTime,
                'in_id' => $in?->id,
                'out_id' => $out?->id,
                'in_original' => $inTime,
                'out_original' => $outTime,
                'holiday' => $holidayNames[$date] ?? '',
            ];
            $cursor = $cursor->addDay();
        }

        return [
            'employment_id' => $employment->id,
            'employee_no' => (string) $employment->employee_no,
            'name' => (string) ($employment->person?->name_with_initials ?? ''),
            'enrolment_missing' => $this->placement($employment, $start) === null,
            'days' => $days,
        ];
    }

    /** @return array{device: Device, device_user_id: int}|null */
    private function placement(Employment $employment, CarbonImmutable $on): ?array
    {
        $day = $on->toDateString();
        $enrolment = BiometricEnrolment::query()
            ->where('employment_id', $employment->id)
            ->whereDate('valid_from', '<=', $day)
            ->where(function ($query) use ($day): void {
                $query->whereNull('valid_to')->orWhereDate('valid_to', '>=', $day);
            })
            ->orderByDesc('valid_from')
            ->first();

        if ($enrolment === null) {
            return null;
        }

        $device = Device::query()
            ->where('device_group', $enrolment->device_group)
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        if ($device === null) {
            return null;
        }

        return ['device' => $device, 'device_user_id' => (int) $enrolment->device_user_id];
    }

    private function applySlot(Device $device, int $deviceUserId, string $date, mixed $punchId, string $original, string $entered): void
    {
        $time = $this->normalize($entered);
        $was = $this->normalize($original);

        if ($time === $was) {
            return;
        }

        $id = is_numeric($punchId) ? (int) $punchId : null;

        if ($time === '') {
            if ($id !== null) {
                $punch = Punch::query()->find($id);
                if ($punch !== null) {
                    $this->punches->delete($punch);
                }
            }

            return;
        }

        $punchedAt = $date.' '.$time;

        if ($id !== null) {
            $punch = Punch::query()->find($id);
            if ($punch !== null) {
                $this->punches->update($punch, $device, $deviceUserId, $punchedAt, $punch->verify_mode, $punch->raw_state);

                return;
            }
        }

        $this->punches->create($device, $deviceUserId, $punchedAt, null, null);
    }

    private function clock(?Punch $punch): string
    {
        if ($punch === null) {
            return '';
        }

        return $punch->punched_at->timezone(config('app.timezone'))->format('H:i:s');
    }

    private function normalize(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (preg_match('/^\d{2}:\d{2}$/', $value) === 1) {
            return $value.':00';
        }

        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $value) === 1) {
            return $value;
        }

        throw new InvalidPunch('Enter times as HH:MM.');
    }

    private function monthStart(string $month): CarbonImmutable
    {
        $start = CarbonImmutable::createFromFormat('!Y-m', $month, config('app.timezone'));
        if ($start === false) {
            throw new InvalidPunch('Choose a month.');
        }

        return $start->startOfMonth();
    }

    /** @return list<Employment> */
    private function oneEmployment(string $employeeNo): array
    {
        $employeeNo = trim($employeeNo);
        if ($employeeNo === '') {
            throw new InvalidPunch('Enter an employee number.');
        }

        $employment = Employment::query()->with('person')->where('employee_no', $employeeNo)->first();

        return $employment === null ? [] : [$employment];
    }

    /** @return list<Employment> */
    private function departmentEmployments(?int $departmentId, CarbonImmutable $on): array
    {
        if ($departmentId === null) {
            throw new InvalidPunch('Select a department.');
        }

        $department = OrgUnit::query()->find($departmentId);
        if ($department === null) {
            throw new InvalidPunch('Select a department.');
        }

        $ids = Assignment::query()
            ->effectiveOn($on->toDateString())
            ->whereIn('org_unit_id', $this->orgUnits->descendantIds($department))
            ->pluck('employment_id');

        return Employment::query()
            ->with('person')
            ->whereIn('id', $ids)
            ->orderBy('employee_no')
            ->get()
            ->all();
    }
}
