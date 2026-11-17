<?php

namespace Modules\Payroll\Livewire;

use App\Exceptions\Domain\DomainException;
use App\Support\Company\CompanyContext;
use Illuminate\View\View;
use Livewire\Component;
use Modules\Organisation\Models\Grade;
use Modules\Payroll\Enums\ExtraDayMethod;
use Modules\Payroll\Models\GradePayRule;
use Modules\Payroll\Services\PayRuleService;
use Modules\Payroll\Support\PayrollNav;

class GradePayRulesIndex extends Component
{
    public bool $showForm = false;

    public string $grade_id = '';

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
        $this->grade_id = '';
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
            'grade_id' => ['required', 'integer'],
            'extra_day_method' => ['required', 'string'],
            'effective_from' => ['required', 'date'],
        ]);

        try {
            $grade = Grade::query()->findOrFail((int) $this->grade_id);
            $rules->setExtraDayMethod($grade, ExtraDayMethod::from($this->extra_day_method), $this->effective_from);
        } catch (DomainException $e) {
            $this->formError = $e->getMessage();

            return;
        }

        $this->closeForm();
    }

    public function render(): View
    {
        $this->authorize('payroll.view');

        return view('payroll::livewire.grade-pay-rules-index', [
            'rules' => GradePayRule::query()->with('grade')->orderByDesc('effective_from')->get(),
            'grades' => Grade::query()->where('is_active', true)->orderBy('rank')->get(),
            'methods' => ExtraDayMethod::cases(),
            'canConfigure' => auth()->user()?->can('payroll.config') ?? false,
        ])->layout('components.layouts.module', [
            'moduleName' => 'Payroll',
            'title' => 'Grade pay rules',
            'moduleNav' => PayrollNav::items('grade-pay-rules'),
        ]);
    }
}
