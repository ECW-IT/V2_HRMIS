<?php

namespace Modules\Payroll\Livewire;

use App\Exceptions\Domain\DomainException;
use App\Support\Company\CompanyContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\View\View;
use Livewire\Component;
use Modules\CoreHr\Models\Employment;
use Modules\Payroll\Enums\PayRunEmployeeStatus;
use Modules\Payroll\Enums\PayRunStatus;
use Modules\Payroll\Exceptions\PayRunBlocked;
use Modules\Payroll\Models\PayComponent;
use Modules\Payroll\Models\PayRun;
use Modules\Payroll\Models\PayRunOverride;
use Modules\Payroll\Services\PayRunOverrideService;
use Modules\Payroll\Services\PayRunService;
use Modules\Payroll\Support\PayrollNav;

class PayRunShow extends Component
{
    public PayRun $run;

    public ?int $selectedEmployeeId = null;

    public string $sendBackReason = '';

    public string $overrideEmploymentId = '';

    public string $overrideCode = '';

    public string $overrideAmount = '';

    public string $overrideReason = '';

    public ?string $formError = null;

    public ?string $notice = null;

    public function mount(PayRun $run): void
    {
        abort_unless(PayrollNav::canSeePayRuns(), 403);
        CompanyContext::ensureSoleSelected();
        $this->run = $run;
    }

    public function calculate(PayRunService $runs): void
    {
        $this->authorize('payrun.manage');
        $this->clearMessages();
        $this->run = $this->run->fresh();

        try {
            $updated = $runs->calculate($this->run, auth()->user());
        } catch (PayRunBlocked $e) {
            $this->formError = $this->blockedMessage($e);

            return;
        } catch (DomainException $e) {
            $this->formError = $e->getMessage();

            return;
        }

        $this->run = $updated;
        $this->selectedEmployeeId = null;
        if ($updated->status === PayRunStatus::DRAFT) {
            $this->notice = 'Calculation has been queued.';
        }
    }

    public function recalculate(int $employmentId, PayRunService $runs): void
    {
        $this->authorize('payrun.manage');
        $this->clearMessages();
        $this->run = $this->run->fresh();

        try {
            $row = $runs->recalculateEmployee($this->run, $employmentId, auth()->user());
        } catch (DomainException $e) {
            $this->formError = $e->getMessage();

            return;
        }

        $this->run = $this->run->fresh();
        $this->selectedEmployeeId = $row->id;
    }

    public function approve(PayRunService $runs): void
    {
        $this->authorize('payrun.approve');
        $this->clearMessages();
        $this->run = $this->run->fresh();

        try {
            $this->run = $runs->approve($this->run, auth()->user());
        } catch (DomainException $e) {
            $this->formError = $e->getMessage();
        }
    }

    public function sendBack(PayRunService $runs): void
    {
        $user = auth()->user();
        abort_unless($user !== null && ($user->can('payrun.approve') || $user->can('payrun.manage')), 403);
        $this->clearMessages();
        $this->run = $this->run->fresh();

        try {
            $this->run = $runs->sendBack($this->run, $user, $this->sendBackReason);
        } catch (DomainException $e) {
            $this->formError = $e->getMessage();

            return;
        }

        $this->sendBackReason = '';
    }

    public function lock(PayRunService $runs): void
    {
        $this->authorize('payrun.lock');
        $this->clearMessages();
        $this->run = $this->run->fresh();

        try {
            $this->run = $runs->lock($this->run, auth()->user());
        } catch (DomainException $e) {
            $this->formError = $e->getMessage();
        }
    }

    public function selectEmployee(int $payRunEmployeeId): void
    {
        $this->selectedEmployeeId = $payRunEmployeeId;
        $employee = $this->run->employees()->whereKey($payRunEmployeeId)->first();
        if ($employee !== null) {
            $this->overrideEmploymentId = (string) $employee->employment_id;
        }
    }

    public function setOverride(PayRunOverrideService $overrides): void
    {
        $this->authorize('payrun.override');
        $this->clearMessages();
        $this->run = $this->run->fresh();

        $this->validate([
            'overrideEmploymentId' => ['required', 'integer'],
            'overrideCode' => ['required', 'string'],
            'overrideAmount' => ['required', 'string'],
            'overrideReason' => ['required', 'string'],
        ]);

        $employment = Employment::query()->findOrFail((int) $this->overrideEmploymentId);

        try {
            $overrides->set(
                $this->run,
                $employment,
                $this->overrideCode,
                $this->overrideAmount,
                $this->overrideReason,
                auth()->user(),
            );
        } catch (DomainException $e) {
            $this->formError = $e->getMessage();

            return;
        } catch (AuthorizationException $e) {
            $this->formError = $e->getMessage();

            return;
        }

        $this->overrideAmount = '';
        $this->overrideReason = '';
        $this->run = $this->run->fresh();
        $this->selectedEmployeeId = $this->run->employees()
            ->where('employment_id', $employment->id)
            ->value('id');
    }

    public function cancelOverride(int $overrideId, PayRunOverrideService $overrides): void
    {
        $this->authorize('payrun.override');
        $this->clearMessages();
        $this->run = $this->run->fresh();

        $override = PayRunOverride::query()
            ->where('pay_run_id', $this->run->id)
            ->findOrFail($overrideId);

        try {
            $overrides->cancel($override, auth()->user());
        } catch (DomainException $e) {
            $this->formError = $e->getMessage();

            return;
        } catch (AuthorizationException $e) {
            $this->formError = $e->getMessage();

            return;
        }

        $this->run = $this->run->fresh();
    }

    public function render(): View
    {
        abort_unless(PayrollNav::canSeePayRuns(), 403);

        $run = PayRun::query()
            ->with([
                'payPeriod',
                'employees' => fn ($q) => $q->orderBy('employee_no')->with(['lines' => fn ($q) => $q->orderBy('display_order')]),
                'events' => fn ($q) => $q->orderBy('id')->with('user'),
                'overrides' => fn ($q) => $q->whereNull('cancelled_at')->with(['payComponent', 'employment']),
            ])
            ->findOrFail($this->run->id);

        $user = auth()->user();
        $openForChange = in_array($run->status, [PayRunStatus::DRAFT, PayRunStatus::CALCULATED], true);

        return view('payroll::livewire.pay-run-show', [
            'run' => $run,
            'components' => PayComponent::query()->where('is_active', true)->orderBy('code')->get(),
            'canManage' => $user?->can('payrun.manage') ?? false,
            'canApprove' => $user?->can('payrun.approve') ?? false,
            'canLock' => $user?->can('payrun.lock') ?? false,
            'canOverride' => $user?->can('payrun.override') ?? false,
            'canExport' => $user?->can('payroll.export') ?? false,
            'canSendBack' => ($user?->can('payrun.approve') ?? false) || ($user?->can('payrun.manage') ?? false),
            'openForChange' => $openForChange,
            'draft' => PayRunStatus::DRAFT,
            'calculated' => PayRunStatus::CALCULATED,
            'approved' => PayRunStatus::APPROVED,
            'employeeOk' => PayRunEmployeeStatus::OK,
        ])->layout('components.layouts.module', [
            'moduleName' => 'Payroll',
            'title' => 'Pay run',
            'moduleNav' => PayrollNav::items('pay-runs'),
        ]);
    }

    private function clearMessages(): void
    {
        $this->formError = null;
        $this->notice = null;
    }

    private function blockedMessage(PayRunBlocked $e): string
    {
        $titles = [];
        foreach ($e->checks as $check) {
            if ($check->blocking && ! $check->ok) {
                $titles[] = $check->title;
            }
        }

        if ($titles === []) {
            return $e->getMessage();
        }

        return $e->getMessage().' '.implode('. ', $titles).'.';
    }
}
