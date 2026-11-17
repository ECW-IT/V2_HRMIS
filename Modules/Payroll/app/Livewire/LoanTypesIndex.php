<?php

namespace Modules\Payroll\Livewire;

use App\Exceptions\Domain\DomainException;
use App\Support\Company\CompanyContext;
use Illuminate\View\View;
use Livewire\Component;
use Modules\Payroll\Models\LoanType;
use Modules\Payroll\Services\LoanTypeService;
use Modules\Payroll\Support\PayrollNav;

class LoanTypesIndex extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $code = '';

    public string $name = '';

    public bool $is_active = true;

    public ?string $formError = null;

    public function boot(): void
    {
        CompanyContext::ensureSoleSelected();
    }

    public function openCreate(): void
    {
        $this->authorize('payroll.config');
        $this->editingId = null;
        $this->code = '';
        $this->name = '';
        $this->is_active = true;
        $this->formError = null;
        $this->showForm = true;
    }

    public function openEdit(int $id): void
    {
        $this->authorize('payroll.config');
        $type = LoanType::query()->findOrFail($id);
        $this->editingId = $type->id;
        $this->code = $type->code;
        $this->name = $type->name;
        $this->is_active = (bool) $type->is_active;
        $this->formError = null;
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->editingId = null;
        $this->formError = null;
    }

    public function save(LoanTypeService $types): void
    {
        $this->authorize('payroll.config');
        $this->formError = null;

        $this->validate([
            'code' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:100'],
            'is_active' => ['boolean'],
        ]);

        try {
            if ($this->editingId === null) {
                $types->create($this->code, $this->name);
            } else {
                $types->update(LoanType::query()->findOrFail($this->editingId), $this->name, $this->is_active);
            }
        } catch (DomainException $e) {
            $this->formError = $e->getMessage();

            return;
        }

        $this->closeForm();
    }

    public function render(): View
    {
        $this->authorize('payroll.view');

        return view('payroll::livewire.loan-types-index', [
            'types' => LoanType::query()->orderBy('code')->get(),
            'canConfigure' => auth()->user()?->can('payroll.config') ?? false,
        ])->layout('components.layouts.module', [
            'moduleName' => 'Payroll',
            'title' => 'Loan types',
            'moduleNav' => PayrollNav::items('loan-types'),
        ]);
    }
}
