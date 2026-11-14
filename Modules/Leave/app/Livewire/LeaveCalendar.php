<?php

namespace Modules\Leave\Livewire;

use Carbon\Carbon;
use Illuminate\View\View;
use Livewire\Component;
use Modules\Attendance\Support\AttendanceNav;
use Modules\Calendar\Services\HolidayService;
use Modules\CoreHr\Support\OrgUnitOptions;
use Modules\Leave\Enums\LeavePortion;
use Modules\Leave\Enums\LeaveStatus;
use Modules\Leave\Models\LeaveRequest;
use Modules\Organisation\Models\OrgUnit;
use Modules\Organisation\Services\OrgUnitService;

class LeaveCalendar extends Component
{
    public string $month = '';

    public string $orgUnitId = '';

    public function mount(): void
    {
        $this->authorize('leave.view');
        $this->month = now()->format('Y-m');
    }

    public function render(): View
    {
        $this->authorize('leave.view');

        return view('leave::livewire.leave-calendar', [
            'units' => OrgUnitOptions::forCompany(null),
            'weeks' => $this->weeks(),
        ])->layout('components.layouts.module', [
            'moduleName' => 'Time & Leave',
            'title' => 'Leave calendar',
            'moduleNav' => AttendanceNav::items('leave-calendar'),
        ]);
    }

    /** @return list<list<array<string, mixed>|null>> */
    private function weeks(): array
    {
        if ($this->orgUnitId === '') {
            return [];
        }

        $unit = OrgUnit::query()->find((int) $this->orgUnitId);
        if ($unit === null) {
            return [];
        }

        $unitIds = app(OrgUnitService::class)->descendantIds($unit);
        $start = Carbon::createFromFormat('Y-m', $this->month)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $requests = LeaveRequest::query()
            ->with(['employment.person', 'leaveType'])
            ->whereIn('status', [LeaveStatus::PENDING, LeaveStatus::APPROVED])
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->get()
            ->filter(function (LeaveRequest $request) use ($unitIds) {
                $assignment = $request->employment?->assignmentOn($request->date);

                return $assignment !== null && in_array((int) $assignment->org_unit_id, $unitIds, true);
            });

        $byDate = [];
        foreach ($requests as $request) {
            $key = $request->date->toDateString();
            $half = $request->portion !== LeavePortion::FULL;
            $byDate[$key][] = [
                'name' => (string) $request->employment?->person?->name_with_initials,
                'status' => $request->status === LeaveStatus::APPROVED ? 'Approved' : 'Pending',
                'half' => $half,
                'label' => $half ? 'Half day' : ($request->status === LeaveStatus::APPROVED ? 'Approved' : 'Pending'),
            ];
        }

        $holidays = app(HolidayService::class);
        $cells = [];
        $cursor = $start->copy()->startOfWeek(Carbon::MONDAY);
        $last = $end->copy()->endOfWeek(Carbon::SUNDAY);
        while ($cursor->lte($last)) {
            $date = $cursor->toDateString();
            $inMonth = $cursor->month === $start->month;
            $people = $byDate[$date] ?? [];
            $approved = collect($people)->where('status', 'Approved')->count();
            $holiday = $inMonth ? $holidays->on((int) $unit->company_id, $date) : null;
            $cells[] = [
                'date' => $date,
                'in_month' => $inMonth,
                'day' => $cursor->day,
                'people' => $people,
                'count' => $approved,
                'holiday' => $holiday?->name,
            ];
            $cursor->addDay();
        }

        return array_chunk($cells, 7);
    }
}
