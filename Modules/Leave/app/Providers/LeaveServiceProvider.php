<?php

namespace Modules\Leave\Providers;

use Livewire\Livewire;
use Modules\Attendance\Contracts\LeaveDayProvider;
use Modules\Leave\Approvals\LeaveRequestHandler;
use Modules\Leave\Enums\LeaveStatus;
use Modules\Leave\Livewire\LeavePoliciesIndex;
use Modules\Leave\Livewire\LeaveTypesIndex;
use Modules\Leave\Models\LeaveRequest;
use Modules\Leave\Services\LeaveService;
use Modules\Leave\Support\LeaveDays;
use Modules\Workflow\Enums\ApprovalStatus;
use Modules\Workflow\Models\ApprovalRequest;
use Modules\Workflow\Services\ApprovalService;
use Nwidart\Modules\Support\ModuleServiceProvider;

class LeaveServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Leave';

    protected string $nameLower = 'leave';

    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function register(): void
    {
        parent::register();

        $this->app->bind(LeaveDayProvider::class, LeaveDays::class);
    }

    public function boot(): void
    {
        parent::boot();

        ApprovalService::registerHandler('LEAVE_REQUEST', LeaveRequestHandler::class);

        ApprovalRequest::updated(function (ApprovalRequest $request): void {
            if ($request->subject_type !== LeaveRequest::class || ! $request->wasChanged('status')) {
                return;
            }

            if ($request->status !== ApprovalStatus::REJECTED) {
                return;
            }

            $leave = LeaveRequest::withoutGlobalScopes()->find($request->subject_id);
            if ($leave !== null && $leave->status === LeaveStatus::PENDING) {
                app(LeaveService::class)->markRejected($leave);
            }
        });

        Livewire::component('leave.types-index', LeaveTypesIndex::class);
        Livewire::component('leave.policies-index', LeavePoliciesIndex::class);
    }
}
