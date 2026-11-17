<?php

namespace Modules\Payroll\Livewire;

use App\Exceptions\Domain\DomainException;
use App\Support\Company\CompanyContext;
use Illuminate\View\View;
use Livewire\Component;
use Modules\Organisation\Models\Company;
use Modules\Payroll\Models\CompanyPaySetting;
use Modules\Payroll\Services\PayRuleService;
use Modules\Payroll\Support\PayrollNav;

class CompanyPaySettingsIndex extends Component
{
    public bool $showForm = false;

    public string $hours_per_month = '240';

    public string $hours_per_day = '8';

    public string $late_divisor = '480';

    public string $attendance_min_offset = '0.0';

    public string $attendance_max_offset = '0.0';

    public string $ot_multiplier = '1.50';

    public string $effective_from = '';

    public ?string $formError = null;

    public function boot(): void
    {
        CompanyContext::ensureSoleSelected();
    }

    public function openCreate(): void
    {
        $this->authorize('payroll.config');
        $this->formError = null;
        $current = CompanyPaySetting::query()->whereNull('effective_to')->orderByDesc('effective_from')->first();
        $this->hours_per_month = $current !== null ? (string) $current->hours_per_month : '240';
        $this->hours_per_day = $current !== null ? (string) $current->hours_per_day : '8';
        $this->late_divisor = $current !== null ? (string) $current->late_divisor : '480';
        $this->attendance_min_offset = $current !== null ? (string) $current->attendance_min_offset : '0.0';
        $this->attendance_max_offset = $current !== null ? (string) $current->attendance_max_offset : '0.0';
        $this->ot_multiplier = $current !== null ? (string) $current->ot_multiplier : '1.50';
        $this->effective_from = '';
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
            'hours_per_month' => ['required', 'integer', 'min:100', 'max:300'],
            'hours_per_day' => ['required', 'integer', 'min:1', 'max:12'],
            'late_divisor' => ['required', 'integer', 'min:1'],
            'attendance_min_offset' => ['required', 'string'],
            'attendance_max_offset' => ['required', 'string'],
            'ot_multiplier' => ['required', 'string'],
            'effective_from' => ['required', 'date'],
        ]);

        $companyId = CompanyContext::id();
        if ($companyId === null) {
            $this->formError = 'Select one company.';

            return;
        }

        try {
            $rules->setCompanySettings(Company::query()->findOrFail($companyId), [
                'hours_per_month' => (int) $this->hours_per_month,
                'hours_per_day' => (int) $this->hours_per_day,
                'late_divisor' => (int) $this->late_divisor,
                'attendance_min_offset' => $this->attendance_min_offset,
                'attendance_max_offset' => $this->attendance_max_offset,
                'ot_multiplier' => $this->ot_multiplier,
            ], $this->effective_from);
        } catch (DomainException $e) {
            $this->formError = $e->getMessage();

            return;
        }

        $this->closeForm();
    }

    public function render(): View
    {
        $this->authorize('payroll.view');

        return view('payroll::livewire.company-pay-settings-index', [
            'rows' => CompanyPaySetting::query()->orderByDesc('effective_from')->get(),
            'needsCompany' => CompanyContext::id() === null,
            'canConfigure' => auth()->user()?->can('payroll.config') ?? false,
        ])->layout('components.layouts.module', [
            'moduleName' => 'Payroll',
            'title' => 'Company pay settings',
            'moduleNav' => PayrollNav::items('company-pay-settings'),
        ]);
    }
}
