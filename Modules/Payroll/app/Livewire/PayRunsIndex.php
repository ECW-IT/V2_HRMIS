<?php

namespace Modules\Payroll\Livewire;

use App\Exceptions\Domain\DomainException;
use App\Support\Company\CompanyContext;
use Illuminate\View\View;
use Livewire\Component;
use Modules\Calendar\Models\PayPeriod;
use Modules\Payroll\Models\PayRun;
use Modules\Payroll\Services\PayRunService;
use Modules\Payroll\Support\PayrollNav;

class PayRunsIndex extends Component
{
    public bool $showForm = false;

    public string $payPeriodId = '';

    public ?string $formError = null;

    public function boot(): void
    {
        CompanyContext::ensureSoleSelected();
    }

    public function openCreate(): void
    {
        $this->authorize('payrun.manage');
        $this->payPeriodId = '';
        $this->formError = null;
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->formError = null;
    }

    public function openRun(PayRunService $runs): void
    {
        $this->authorize('payrun.manage');
        $this->formError = null;

        $this->validate([
            'payPeriodId' => ['required', 'integer'],
        ]);

        $period = PayPeriod::query()->findOrFail((int) $this->payPeriodId);

        try {
            $runs->open($period, auth()->user());
        } catch (DomainException $e) {
            $this->formError = $e->getMessage();

            return;
        }

        $this->closeForm();
    }

    public function render(): View
    {
        abort_unless(PayrollNav::canSeePayRuns(), 403);

        $runs = PayRun::query()->with('payPeriod')->orderByDesc('id')->get();
        $taken = $runs->pluck('pay_period_id');
        $periods = PayPeriod::query()->orderByDesc('start_date');
        if ($taken->isNotEmpty()) {
            $periods->whereNotIn('id', $taken);
        }

        return view('payroll::livewire.pay-runs-index', [
            'runs' => $runs,
            'periods' => $periods->get(),
            'canManage' => auth()->user()?->can('payrun.manage') ?? false,
        ])->layout('components.layouts.module', [
            'moduleName' => 'Payroll',
            'title' => 'Pay runs',
            'moduleNav' => PayrollNav::items('pay-runs'),
        ]);
    }
}
