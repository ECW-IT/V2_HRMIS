<?php

namespace Modules\Attendance\Livewire;

use App\Exceptions\Domain\DomainException;
use Carbon\CarbonImmutable;
use Illuminate\View\View;
use Livewire\Component;
use Modules\Attendance\Services\MonthPunchService;
use Modules\Attendance\Support\AttendanceNav;
use Modules\CoreHr\Enums\EmploymentStatus;
use Modules\CoreHr\Models\Employment;
use Modules\Organisation\Enums\OrgUnitType;
use Modules\Organisation\Models\OrgUnit;

class MonthPunches extends Component
{
    public string $mode = 'employee';

    public string $month = '';

    public string $employeeNo = '';

    public string $departmentId = '';

    /** @var list<array<string, mixed>> */
    public array $rows = [];

    public bool $loaded = false;

    public bool $locked = false;

    public string $notice = '';

    public function mount(): void
    {
        $this->authorize('attendance.punch.view');
        $this->month = CarbonImmutable::now(config('app.timezone'))->format('Y-m');
    }

    public function render(): View
    {
        $this->authorize('attendance.punch.view');

        return view('attendance::livewire.month-punches', [
            'employeeOptions' => Employment::query()
                ->with('person:id,name_with_initials')
                ->where('status', EmploymentStatus::ACTIVE)
                ->orderBy('employee_no')
                ->get(['id', 'person_id', 'employee_no'])
                ->map(fn (Employment $employee) => [
                    'id' => $employee->employee_no,
                    'label' => $employee->employee_no.' — '.($employee->person?->name_with_initials ?? ''),
                ])
                ->all(),
            'departments' => OrgUnit::query()
                ->where('type', OrgUnitType::DEPARTMENT)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'canAdjust' => auth()->user()?->can('attendance.adjust') ?? false,
        ])->layout('components.layouts.module', [
            'moduleName' => 'Attendance',
            'title' => 'Month entry',
            'moduleNav' => AttendanceNav::items('month-punches'),
        ]);
    }

    public function loadMonth(MonthPunchService $months): void
    {
        $this->authorize('attendance.punch.view');
        $this->notice = '';

        try {
            $result = $months->load(
                $this->mode,
                $this->month,
                $this->employeeNo,
                $this->departmentId !== '' ? (int) $this->departmentId : null,
            );
        } catch (DomainException $e) {
            $this->rows = [];
            $this->loaded = false;
            $this->locked = false;
            $this->notice = $e->getMessage();

            return;
        }

        $this->rows = $result['rows'];
        $this->locked = $result['locked'];
        $this->loaded = true;
        if ($this->locked) {
            $this->notice = 'This pay period is closed.';
        }
    }

    public function saveMonth(MonthPunchService $months): void
    {
        $this->authorize('attendance.adjust');

        try {
            $this->notice = $months->save($this->month, $this->rows);
            $result = $months->load(
                $this->mode,
                $this->month,
                $this->employeeNo,
                $this->departmentId !== '' ? (int) $this->departmentId : null,
            );
            $this->rows = $result['rows'];
            $this->locked = $result['locked'];
        } catch (DomainException $e) {
            $this->notice = $e->getMessage();
        }
    }
}
