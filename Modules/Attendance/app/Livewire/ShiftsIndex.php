<?php

namespace Modules\Attendance\Livewire;

use Illuminate\View\View;
use Livewire\Component;
use Modules\Attendance\Exceptions\InvalidShift;
use Modules\Attendance\Models\EmploymentTimeProfile;
use Modules\Attendance\Models\Shift;
use Modules\Attendance\Services\ShiftService;
use Modules\Attendance\Support\AttendanceNav;

class ShiftsIndex extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $code = '';

    public string $name = '';

    public string $start_time = '08:00';

    public string $end_time = '17:00';

    public string $late_from = '08:06';

    public string $morning_cutoff = '10:00';

    public string $half_day_from = '12:30';

    public string $early_leave_from = '15:00';

    /** @var list<array{name: string, start_time: string, end_time: string, is_paid: bool}> */
    public array $breaks = [];

    public string $formError = '';

    public function openCreate(): void
    {
        $this->authorize('shift.manage');
        $this->editingId = null;
        $this->resetForm();
        $this->showForm = true;
    }

    public function openEdit(int $shiftId): void
    {
        $this->authorize('shift.manage');
        $shift = Shift::query()->with('breaks')->findOrFail($shiftId);
        $this->editingId = $shift->id;
        $this->code = $shift->code;
        $this->name = $shift->name;
        $this->start_time = substr((string) $shift->start_time, 0, 5);
        $this->end_time = substr((string) $shift->end_time, 0, 5);
        $this->late_from = substr((string) $shift->late_from, 0, 5);
        $this->morning_cutoff = substr((string) $shift->morning_cutoff, 0, 5);
        $this->half_day_from = substr((string) $shift->half_day_from, 0, 5);
        $this->early_leave_from = substr((string) $shift->early_leave_from, 0, 5);
        $this->breaks = $shift->breaks->map(fn ($b) => [
            'name' => $b->name,
            'start_time' => substr((string) $b->start_time, 0, 5),
            'end_time' => substr((string) $b->end_time, 0, 5),
            'is_paid' => (bool) $b->is_paid,
        ])->values()->all();
        $this->formError = '';
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->editingId = null;
        $this->formError = '';
        $this->resetForm();
    }

    public function addBreakRow(): void
    {
        $this->breaks[] = ['name' => '', 'start_time' => '20:00', 'end_time' => '20:30', 'is_paid' => false];
    }

    public function removeBreakRow(int $index): void
    {
        unset($this->breaks[$index]);
        $this->breaks = array_values($this->breaks);
    }

    public function save(ShiftService $service): void
    {
        $this->authorize('shift.manage');
        $this->formError = '';

        $validated = $this->validate([
            'code' => ['required', 'string', 'max:10'],
            'name' => ['required', 'string', 'max:100'],
            'start_time' => ['required'],
            'end_time' => ['required'],
            'late_from' => ['required'],
            'morning_cutoff' => ['required'],
            'half_day_from' => ['required'],
            'early_leave_from' => ['required'],
            'breaks' => ['array'],
            'breaks.*.name' => ['required', 'string', 'max:50'],
            'breaks.*.start_time' => ['required'],
            'breaks.*.end_time' => ['required'],
            'breaks.*.is_paid' => ['boolean'],
        ]);

        $data = [
            'code' => $validated['code'],
            'name' => $validated['name'],
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'late_from' => $validated['late_from'],
            'morning_cutoff' => $validated['morning_cutoff'],
            'half_day_from' => $validated['half_day_from'],
            'early_leave_from' => $validated['early_leave_from'],
        ];

        try {
            if ($this->editingId === null) {
                $service->create($data, $validated['breaks'] ?? []);
            } else {
                $service->update(Shift::query()->findOrFail($this->editingId), $data, $validated['breaks'] ?? []);
            }
        } catch (InvalidShift $e) {
            $this->formError = $e->getMessage();

            return;
        }

        $this->closeForm();
    }

    public function deactivate(int $shiftId, ShiftService $service): void
    {
        $this->authorize('shift.manage');
        try {
            $service->deactivate(Shift::query()->findOrFail($shiftId));
        } catch (InvalidShift $e) {
            $this->formError = $e->getMessage();
        }
    }

    public function render(): View
    {
        $this->authorize('org.view');

        $shifts = Shift::query()->with('breaks')->orderBy('code')->get()->map(function (Shift $shift) {
            $employeesNow = EmploymentTimeProfile::query()
                ->where('shift_id', $shift->id)
                ->current()
                ->count();

            return [
                'shift' => $shift,
                'employees_now' => $employeesNow,
            ];
        });

        return view('attendance::livewire.shifts-index', [
            'rows' => $shifts,
            'preview' => $this->previewText(),
        ])->layout('components.layouts.module', [
            'moduleName' => 'Attendance',
            'title' => 'Shifts',
            'moduleNav' => AttendanceNav::items('shifts'),
        ]);
    }

    protected function resetForm(): void
    {
        $this->code = '';
        $this->name = '';
        $this->start_time = '08:00';
        $this->end_time = '17:00';
        $this->late_from = '08:06';
        $this->morning_cutoff = '10:00';
        $this->half_day_from = '12:30';
        $this->early_leave_from = '15:00';
        $this->breaks = [];
    }

    private function previewText(): string
    {
        $morning = $this->morning_cutoff !== '' ? $this->morning_cutoff : '—';
        $end = $this->end_time !== '' ? $this->end_time : '—';
        $early = $this->early_leave_from !== '' ? $this->early_leave_from : '—';
        $half = $this->half_day_from !== '' ? $this->half_day_from : '—';

        return "Arrive by {$morning} and leave at {$end} or later → full day. Leave between {$early} and {$end} → full day, short leave. Leave between {$half} and {$early} → half day.";
    }
}
