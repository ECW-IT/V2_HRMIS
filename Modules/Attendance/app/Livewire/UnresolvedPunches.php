<?php

namespace Modules\Attendance\Livewire;

use App\Support\Company\CompanyContext;
use Illuminate\View\View;
use Livewire\Component;
use Modules\Attendance\Enums\PunchResolution;
use Modules\Attendance\Models\Punch;
use Modules\Attendance\Services\EnrolmentService;
use Modules\CoreHr\Enums\EmploymentStatus;
use Modules\CoreHr\Models\Employment;

class UnresolvedPunches extends Component
{
    public function enrol(string $deviceGroup, int $deviceUserId, int $employmentId, string $from, EnrolmentService $enrolments): void
    {
        $this->authorize('attendance.device.manage');

        $employment = Employment::query()->findOrFail($employmentId);
        abort_unless(in_array((int) $employment->company_id, CompanyContext::permittedIds(), true), 403);

        $enrolments->enrol($employment, $deviceGroup, $deviceUserId, $from);
    }

    public function render(): View
    {
        $this->authorize('attendance.device.manage');

        $groups = Punch::query()
            ->selectRaw('devices.device_group, punches.device_user_id, COUNT(*) as punch_count, MIN(punches.punched_at) as first_at, MAX(punches.punched_at) as last_at')
            ->join('devices', 'devices.id', '=', 'punches.device_id')
            ->where('punches.resolution', PunchResolution::UNRESOLVED)
            ->groupBy('devices.device_group', 'punches.device_user_id')
            ->orderBy('devices.device_group')
            ->orderBy('punches.device_user_id')
            ->get();

        $employees = Employment::query()
            ->with('person')
            ->whereIn('status', [EmploymentStatus::ACTIVE, EmploymentStatus::SUSPENDED])
            ->whereIn('company_id', CompanyContext::permittedIds())
            ->orderBy('employee_no')
            ->get();

        return view('attendance::livewire.unresolved-punches', [
            'groups' => $groups,
            'employees' => $employees,
        ])->layout('components.layouts.app', [
            'title' => 'Unresolved punches',
        ]);
    }
}
