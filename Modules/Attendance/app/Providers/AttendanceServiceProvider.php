<?php

namespace Modules\Attendance\Providers;

use Livewire\Livewire;
use Modules\Attendance\Approvals\AttendanceAdjustmentHandler;
use Modules\Attendance\Console\ComputeTimecards;
use Modules\Attendance\Console\ResolvePendingPunches;
use Modules\Attendance\Contracts\LeaveDayProvider;
use Modules\Attendance\Enums\AdjustmentStatus;
use Modules\Attendance\Http\Middleware\AuthenticateDevice;
use Modules\Attendance\Livewire\DailyBoard;
use Modules\Attendance\Livewire\DevicesIndex;
use Modules\Attendance\Livewire\EmployeePunches;
use Modules\Attendance\Livewire\EmployeeTimecards;
use Modules\Attendance\Livewire\Exceptions;
use Modules\Attendance\Livewire\LateReview;
use Modules\Attendance\Livewire\OtApproval;
use Modules\Attendance\Livewire\PeriodClose;
use Modules\Attendance\Livewire\PeriodSummary;
use Modules\Attendance\Livewire\ShiftsIndex;
use Modules\Attendance\Livewire\Tabs\TimeProfile;
use Modules\Attendance\Livewire\TimeProfilesBulk;
use Modules\Attendance\Livewire\UnresolvedPunches;
use Modules\Attendance\Models\AttendanceAdjustment;
use Modules\Attendance\Services\AdjustmentService;
use Modules\Attendance\Support\NullLeaveDayProvider;
use Modules\Workflow\Enums\ApprovalStatus;
use Modules\Workflow\Models\ApprovalRequest;
use Modules\Workflow\Services\ApprovalService;
use Nwidart\Modules\Support\ModuleServiceProvider;

class AttendanceServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Attendance';

    protected string $nameLower = 'attendance';

    protected array $commands = [
        ResolvePendingPunches::class,
        ComputeTimecards::class,
    ];

    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function register(): void
    {
        parent::register();

        $this->app->bind(LeaveDayProvider::class, NullLeaveDayProvider::class);
    }

    public function boot(): void
    {
        parent::boot();

        $this->app['router']->aliasMiddleware('device.auth', AuthenticateDevice::class);

        ApprovalService::registerHandler('ATTENDANCE_ADJUSTMENT', AttendanceAdjustmentHandler::class);

        ApprovalRequest::updated(function (ApprovalRequest $request): void {
            if ($request->subject_type !== AttendanceAdjustment::class || ! $request->wasChanged('status')) {
                return;
            }

            if ($request->status !== ApprovalStatus::REJECTED) {
                return;
            }

            $adjustment = AttendanceAdjustment::withoutGlobalScopes()->find($request->subject_id);
            if ($adjustment !== null && $adjustment->status === AdjustmentStatus::PENDING) {
                app(AdjustmentService::class)->markRejected($adjustment);
            }
        });

        Livewire::component('attendance.devices-index', DevicesIndex::class);
        Livewire::component('attendance.unresolved-punches', UnresolvedPunches::class);
        Livewire::component('attendance.employee-punches', EmployeePunches::class);
        Livewire::component('attendance.shifts-index', ShiftsIndex::class);
        Livewire::component('attendance.time-profiles-bulk', TimeProfilesBulk::class);
        Livewire::component('attendance.tabs.time-profile', TimeProfile::class);
        Livewire::component('attendance.daily-board', DailyBoard::class);
        Livewire::component('attendance.employee-timecards', EmployeeTimecards::class);
        Livewire::component('attendance.exceptions', Exceptions::class);
        Livewire::component('attendance.late-review', LateReview::class);
        Livewire::component('attendance.ot-approval', OtApproval::class);
        Livewire::component('attendance.period-close', PeriodClose::class);
        Livewire::component('attendance.period-summary', PeriodSummary::class);
    }
}
