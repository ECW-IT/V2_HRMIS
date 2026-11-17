<?php

namespace Modules\Payroll\Livewire;

use App\Exceptions\Domain\DomainException;
use App\Support\Company\CompanyContext;
use Illuminate\View\View;
use Livewire\Component;
use Modules\Calendar\Models\PayPeriod;
use Modules\CoreHr\Models\Employment;
use Modules\Payroll\Enums\AdvanceStatus;
use Modules\Payroll\Models\SalaryAdvance;
use Modules\Payroll\Services\AdvanceService;
use Modules\Payroll\Support\PayrollNav;

class SalaryAdvancesIndex extends Component
{
    public bool $showForm = false;

    public string $employmentId = '';

    public string $payPeriodId = '';

    public string $amount = '';

    public string $reason = '';

    public string $paidOn = '';

    public string $paymentReference = '';

    public ?string $formError = null;

    public function boot(): void
    {
        CompanyContext::ensureSoleSelected();
    }

    public function openCreate(): void
    {
        $this->authorize('advance.manage');
        $this->formError = null;
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->formError = null;
    }

    public function save(AdvanceService $advances): void
    {
        $this->authorize('advance.manage');
        $this->formError = null;

        $this->validate([
            'employmentId' => ['required', 'integer'],
            'payPeriodId' => ['required', 'integer'],
            'amount' => ['required', 'string'],
            'reason' => ['nullable', 'string'],
        ]);

        try {
            $advances->request(
                Employment::query()->findOrFail((int) $this->employmentId),
                PayPeriod::query()->findOrFail((int) $this->payPeriodId),
                $this->amount,
                $this->reason !== '' ? $this->reason : null,
                auth()->user(),
            );
        } catch (DomainException $e) {
            $this->formError = $e->getMessage();

            return;
        }

        $this->closeForm();
    }

    public function markPaid(int $advanceId, AdvanceService $advances): void
    {
        $this->authorize('advance.pay');
        $this->formError = null;

        try {
            $advances->markPaid(
                SalaryAdvance::query()->findOrFail($advanceId),
                $this->paidOn,
                $this->paymentReference !== '' ? $this->paymentReference : null,
                auth()->user(),
            );
        } catch (DomainException $e) {
            $this->formError = $e->getMessage();
        }
    }

    public function cancel(int $advanceId, AdvanceService $advances): void
    {
        $this->authorize('advance.manage');
        $this->formError = null;

        try {
            $advances->cancel(SalaryAdvance::query()->findOrFail($advanceId), auth()->user());
        } catch (DomainException $e) {
            $this->formError = $e->getMessage();
        }
    }

    public function render(): View
    {
        $this->authorize('advance.manage');

        $rows = SalaryAdvance::query()->with(['employment.person', 'payPeriod'])->orderByDesc('id');
        if ($this->payPeriodId !== '') {
            $rows->where('pay_period_id', (int) $this->payPeriodId);
        }

        return view('payroll::livewire.salary-advances-index', [
            'advances' => $rows->get(),
            'employments' => Employment::query()->with('person')->orderBy('employee_no')->get(),
            'periods' => PayPeriod::query()->orderByDesc('start_date')->get(),
            'canPay' => auth()->user()?->can('advance.pay') ?? false,
            'pending' => AdvanceStatus::PENDING,
            'approved' => AdvanceStatus::APPROVED,
        ])->layout('components.layouts.module', [
            'moduleName' => 'Payroll',
            'title' => 'Salary advances',
            'moduleNav' => PayrollNav::items('advances'),
        ]);
    }
}
