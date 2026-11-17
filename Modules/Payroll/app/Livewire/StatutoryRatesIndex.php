<?php

namespace Modules\Payroll\Livewire;

use App\Exceptions\Domain\DomainException;
use App\Support\Company\CompanyContext;
use Illuminate\View\View;
use Livewire\Component;
use Modules\Payroll\Models\StatutoryRate;
use Modules\Payroll\Services\PayRuleService;
use Modules\Payroll\Support\PayrollNav;

class StatutoryRatesIndex extends Component
{
    public bool $showForm = false;

    public string $code = 'EPF_EE';

    public string $percent = '';

    public string $effective_from = '';

    public ?string $formError = null;

    public function boot(): void
    {
        CompanyContext::ensureSoleSelected();
    }

    public function openCreate(): void
    {
        $this->authorize('payroll.config');
        $this->code = 'EPF_EE';
        $this->percent = '';
        $this->effective_from = '';
        $this->formError = null;
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->formError = null;
    }

    public function save(PayRuleService $rules): void
    {
        $this->authorize('payroll.config');
        $this->formError = null;

        $this->validate([
            'code' => ['required', 'string', 'max:20'],
            'percent' => ['required', 'string'],
            'effective_from' => ['required', 'date'],
        ]);

        try {
            $rules->setStatutoryRate(strtoupper(trim($this->code)), $this->percent, $this->effective_from);
        } catch (DomainException $e) {
            $this->formError = $e->getMessage();

            return;
        }

        $this->closeForm();
    }

    public function render(): View
    {
        $this->authorize('payroll.view');

        return view('payroll::livewire.statutory-rates-index', [
            'rates' => StatutoryRate::query()->orderBy('code')->orderByDesc('effective_from')->get(),
            'canConfigure' => auth()->user()?->can('payroll.config') ?? false,
        ])->layout('components.layouts.module', [
            'moduleName' => 'Payroll',
            'title' => 'Statutory rates',
            'moduleNav' => PayrollNav::items('statutory-rates'),
        ]);
    }
}
