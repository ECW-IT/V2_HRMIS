<?php

namespace Modules\Attendance\Livewire;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Attendance\Enums\DayType;
use Modules\Attendance\Enums\PunchResolution;
use Modules\Attendance\Enums\TimecardException;
use Modules\Attendance\Exports\DailyBoardExport;
use Modules\Attendance\Models\Punch;
use Modules\Attendance\Models\Shift;
use Modules\Attendance\Models\Timecard;
use Modules\Attendance\Services\TimeProfileService;
use Modules\Attendance\Support\AttendanceNav;
use Modules\Attendance\Timecards\WorkDate;
use Modules\Calendar\Services\HolidayService;
use Modules\CoreHr\Models\Employment;
use Modules\CoreHr\Support\OrgUnitOptions;
use Modules\Leave\Enums\LeaveStatus;
use Modules\Leave\Models\LeaveRequest;
use Modules\Organisation\Models\OrgUnit;
use Modules\Organisation\Services\OrgUnitService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DailyBoard extends Component
{
    public string $date = '';

    public string $orgUnitId = '';

    public string $shiftId = '';

    public string $status = '';

    public function mount(): void
    {
        $this->authorize('timecard.view');
        $this->date = now()->toDateString();
    }

    public function export(): BinaryFileResponse
    {
        $this->authorize('timecard.view');

        return Excel::download(new DailyBoardExport($this->build()['rows']), 'daily-board.xlsx');
    }

    public function render(): View
    {
        $this->authorize('timecard.view');
        $built = $this->build();

        return view('attendance::livewire.daily-board', [
            'rows' => $built['rows'],
            'cards' => $built['cards'],
            'units' => OrgUnitOptions::forCompany(null),
            'shifts' => Shift::query()->where('is_active', true)->orderBy('code')->get(),
            'statuses' => ['In', 'Present', 'Absent', 'On leave', 'Missing punch', 'Too little presence', 'No time profile', 'Half', 'Short'],
        ])->layout('components.layouts.module', [
            'moduleName' => 'Time & Leave',
            'title' => 'Daily board',
            'moduleNav' => AttendanceNav::items('board'),
        ]);
    }

    /**
     * @return array{rows: list<array<string, string>>, cards: array<string, int>}
     */
    private function build(): array
    {
        $date = $this->safeDate();
        $employments = $this->employments($date);
        $emptyCards = [
            'Present' => 0,
            'Absent' => 0,
            'On leave' => 0,
            'Missing punch' => 0,
            'Late' => 0,
            'No time profile' => 0,
        ];

        if ($employments->isEmpty()) {
            return ['rows' => [], 'cards' => $emptyCards];
        }

        $ids = $employments->pluck('id')->all();
        [$from, $to] = WorkDate::window($date);
        $isToday = $date === Carbon::now(config('app.timezone'))->toDateString();

        $punchCounts = Punch::query()
            ->whereIn('employment_id', $ids)
            ->where('resolution', PunchResolution::RESOLVED)
            ->where('punched_at', '>=', $from)
            ->where('punched_at', '<', $to)
            ->groupBy('employment_id')
            ->selectRaw('employment_id, COUNT(*) as punch_count, MIN(punched_at) as first_at, MAX(punched_at) as last_at')
            ->get()
            ->keyBy('employment_id');

        $timecards = Timecard::query()
            ->whereIn('employment_id', $ids)
            ->whereDate('work_date', $date)
            ->get()
            ->keyBy('employment_id');

        $onLeave = LeaveRequest::query()
            ->whereIn('employment_id', $ids)
            ->whereDate('date', $date)
            ->where('status', LeaveStatus::APPROVED)
            ->pluck('employment_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $profiles = app(TimeProfileService::class);
        $holidays = app(HolidayService::class);
        $holidayName = [];
        $rows = [];
        $cards = $emptyCards;

        foreach ($employments as $employment) {
            $profile = $profiles->on($employment, $date);
            $profile?->loadMissing('shift');
            $card = $timecards->get($employment->id);
            $counts = $punchCounts->get($employment->id);
            $punchCount = (int) ($counts->punch_count ?? 0);
            $noProfile = $profile === null;
            $late = $card !== null && ((int) $card->late_in_minutes + (int) $card->early_out_minutes) > 0;

            if (in_array((int) $employment->id, $onLeave, true)) {
                $base = 'On leave';
            } elseif ($isToday) {
                $base = $punchCount >= 2 ? 'Present' : ($punchCount === 1 ? 'In' : 'Absent');
            } else {
                $base = $this->pastStatus($card);
            }

            $companyId = (int) $employment->company_id;
            if (! array_key_exists($companyId, $holidayName)) {
                $holidayName[$companyId] = $holidays->on($companyId, $date)?->name;
            }

            $status = $holidayName[$companyId] !== null
                ? $holidayName[$companyId].' · '.$base
                : $base;

            $assignment = $employment->assignmentOn($date);
            $first = $isToday
                ? $this->clock($counts->first_at ?? null)
                : ($card?->first_in?->timezone(config('app.timezone'))->format('H:i') ?? '');
            $last = $isToday
                ? ($punchCount >= 2 ? $this->clock($counts->last_at ?? null) : '')
                : ($card?->last_out?->timezone(config('app.timezone'))->format('H:i') ?? '');

            if (isset($cards[$base]) && $base !== 'No time profile') {
                $cards[$base]++;
            }
            if ($late) {
                $cards['Late']++;
            }
            if ($noProfile || $base === 'No time profile') {
                $cards['No time profile']++;
            }

            if ($this->status !== '' && $this->status !== $base) {
                continue;
            }

            $rows[] = [
                'id' => (string) $employment->id,
                'employee_no' => (string) $employment->employee_no,
                'name' => (string) $employment->person?->name_with_initials,
                'org_unit' => (string) ($assignment?->orgUnit?->name ?? ''),
                'shift' => (string) ($profile?->shift?->code ?? ''),
                'status' => $status,
                'first_in' => $first,
                'last_out' => $last,
                'late' => $late ? (string) ((int) $card->late_in_minutes + (int) $card->early_out_minutes) : '',
                'month' => substr($date, 0, 7),
            ];
        }

        return ['rows' => $rows, 'cards' => $cards];
    }

    private function pastStatus(?Timecard $card): string
    {
        if ($card === null) {
            return 'Absent';
        }

        return match ($card->exception) {
            TimecardException::MISSING_PUNCH => 'Missing punch',
            TimecardException::NO_PROFILE => 'No time profile',
            TimecardException::SHORT_PRESENCE => 'Too little presence',
            default => match ($card->day_type) {
                DayType::FULL => 'Present',
                DayType::HALF => 'Half',
                DayType::SHORT => 'Short',
                default => (int) $card->punch_count === 0 ? 'Absent' : 'None',
            },
        };
    }

    /** @return Collection<int, Employment> */
    private function employments(string $date): Collection
    {
        $unitIds = [];
        if ($this->orgUnitId !== '') {
            $unit = OrgUnit::query()->find((int) $this->orgUnitId);
            if ($unit !== null) {
                $unitIds = app(OrgUnitService::class)->descendantIds($unit);
            }
        }

        return Employment::query()
            ->with('person')
            ->whereDate('hire_date', '<=', $date)
            ->where(function ($query) use ($date) {
                $query->whereNull('exit_date')->orWhereDate('exit_date', '>=', $date);
            })
            ->whereHas('assignments', function ($query) use ($date, $unitIds) {
                $query->whereDate('effective_from', '<=', $date)
                    ->where(function ($query) use ($date) {
                        $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date);
                    });
                if ($unitIds !== []) {
                    $query->whereIn('org_unit_id', $unitIds);
                }
            })
            ->when($this->shiftId !== '', function ($query) use ($date) {
                $query->whereHas('timeProfiles', function ($query) use ($date) {
                    $query->where('shift_id', (int) $this->shiftId)
                        ->whereDate('effective_from', '<=', $date)
                        ->where(function ($query) use ($date) {
                            $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date);
                        });
                });
            })
            ->orderBy('employee_no')
            ->get();
    }

    private function safeDate(): string
    {
        try {
            return Carbon::parse($this->date)->toDateString();
        } catch (\Throwable) {
            $this->date = now()->toDateString();

            return $this->date;
        }
    }

    private function clock(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return Carbon::parse($value)->timezone(config('app.timezone'))->format('H:i');
    }
}
