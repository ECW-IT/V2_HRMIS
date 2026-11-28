<?php

namespace Modules\Payroll\Livewire;

use App\Exceptions\Domain\DomainException;
use App\Support\Company\CompanyContext;
use Illuminate\View\View;
use Livewire\Component;
use Modules\Organisation\Models\EmployeeCategory;
use Modules\Payroll\Enums\ExtraDayMethod;
use Modules\Payroll\Models\CategoryPayRule;
use Modules\Payroll\Services\PayRuleService;
use Modules\Payroll\Support\PayrollNav;

class CategoryPayRulesIndex extends Component
{
    public bool $showForm = false;

    public string $employee_category_id = '';

    public string $extra_day_method = 'HOURLY';

    public string $effective_from = '';

    public ?string $formError = null;

    public function boot(): void
    {
        CompanyContext::ensureSoleSelected();
    }

    public function openCreate(): void
    {
        $this->authorize('payroll.config');
        $this->employee_category_id = '';
        $this->extra_day_method = ExtraDayMethod::HOURLY->value;
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
            'employee_category_id' => ['required', 'integer'],
            'extra_day_method' => ['required', 'string'],
            'effective_from' => ['required', 'date'],
        ]);

        try {
            $category = EmployeeCategory::query()->findOrFail((int) $this->employee_category_id);
            $rules->setExtraDayMethod($category, ExtraDayMethod::from($this->extra_day_method), $this->effective_from);
        } catch (DomainException $e) {
            $this->formError = $e->getMessage();

            return;
        }

        $this->closeForm();
    }

    public function render(): View
    {
        $this->authorize('payroll.view');

        return view('payroll::livewire.category-pay-rules-index', [
            'rules' => CategoryPayRule::query()->with('employeeCategory')->orderByDesc('effective_from')->get(),
            'categories' => EmployeeCategory::query()->where('is_active', true)->orderBy('level')->orderBy('name')->get(),
            'methods' => ExtraDayMethod::cases(),
            'canConfigure' => auth()->user()?->can('payroll.config') ?? false,
        ])->layout('components.layouts.module', [
            'moduleName' => 'Payroll',
            'title' => 'Category pay rules',
            'moduleNav' => PayrollNav::items('category-pay-rules'),
        ]);
    }
}
