<?php

namespace Modules\Payroll\Livewire;

use App\Exceptions\Domain\DomainException;
use App\Support\Company\CompanyContext;
use Illuminate\View\View;
use Livewire\Component;
use Modules\Payroll\Models\AttendanceAllowanceTier;
use Modules\Payroll\Services\PayRuleService;
use Modules\Payroll\Support\PayrollNav;

class AttendanceTiersIndex extends Component
{
    public bool $showForm = false;

    public string $code = '';

    public string $name = '';

    public string $base_amount = '';

    public string $per_day_rate = '';

    public string $effective_from = '';

    public ?string $formError = null;

    public function boot(): void
    {
        CompanyContext::ensureSoleSelected();
    }

    public function openCreate(): void
    {
        $this->authorize('payroll.config');
        $this->code = '';
        $this->name = '';
        $this->base_amount = '';
        $this->per_day_rate = '';
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
            'name' => ['required', 'string', 'max:100'],
            'base_amount' => ['required', 'string'],
            'per_day_rate' => ['required', 'string'],
            'effective_from' => ['required', 'date'],
        ]);

        try {
            $rules->setTier(
                strtoupper(trim($this->code)),
                $this->name,
                $this->base_amount,
                $this->per_day_rate,
                $this->effective_from,
            );
        } catch (DomainException $e) {
            $this->formError = $e->getMessage();

            return;
        }

        $this->closeForm();
    }

    public function render(): View
    {
        $this->authorize('payroll.view');

        return view('payroll::livewire.attendance-tiers-index', [
            'tiers' => AttendanceAllowanceTier::query()->orderBy('code')->orderByDesc('effective_from')->get(),
            'canConfigure' => auth()->user()?->can('payroll.config') ?? false,
        ])->layout('components.layouts.module', [
            'moduleName' => 'Payroll',
            'title' => 'Attendance allowance tiers',
            'moduleNav' => PayrollNav::items('attendance-tiers'),
        ]);
    }
}
