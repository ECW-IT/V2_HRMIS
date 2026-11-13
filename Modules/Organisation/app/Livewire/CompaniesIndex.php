<?php

namespace Modules\Organisation\Livewire;

use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;
use Modules\Organisation\Models\Company;
use Modules\Organisation\Services\CompanyService;
use Modules\Organisation\Support\OrganisationNav;

class CompaniesIndex extends Component
{
    public string $search = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $formCode = '';

    public string $formName = '';

    public string $formPrefix = '';

    public int $formPadding = 3;

    public string $formEpf = '';

    public string $formEtf = '';

    public string $formTin = '';

    public bool $formIsActive = true;

    public ?int $formNextSeq = null;

    public function mount(): void
    {
        abort_unless(auth()->user()?->hasRole('super-admin'), 403);
        $this->authorize('org.view');
    }

    public function openCreate(): void
    {
        $this->authorize('org.manage');
        $this->editingId = null;
        $this->resetForm();
        $this->showForm = true;
    }

    public function openEdit(int $companyId): void
    {
        $this->authorize('org.manage');
        $company = Company::query()->findOrFail($companyId);
        $this->editingId = $company->id;
        $this->formCode = $company->code;
        $this->formName = $company->name;
        $this->formPrefix = $company->employee_no_prefix;
        $this->formPadding = (int) $company->employee_no_padding;
        $this->formEpf = (string) ($company->epf_employer_no ?? '');
        $this->formEtf = (string) ($company->etf_employer_no ?? '');
        $this->formTin = (string) ($company->tin ?? '');
        $this->formIsActive = (bool) $company->is_active;
        $this->formNextSeq = (int) $company->next_employee_seq;
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->editingId = null;
        $this->resetForm();
    }

    protected function resetForm(): void
    {
        $this->formCode = '';
        $this->formName = '';
        $this->formPrefix = '';
        $this->formPadding = 3;
        $this->formEpf = '';
        $this->formEtf = '';
        $this->formTin = '';
        $this->formIsActive = true;
        $this->formNextSeq = null;
    }

    public function save(CompanyService $companies): void
    {
        $this->authorize('org.manage');

        $validated = $this->validate([
            'formCode' => ['required', 'string', 'max:10', Rule::unique('companies', 'code')->ignore($this->editingId)],
            'formName' => ['required', 'string', 'max:150'],
            'formPrefix' => ['required', 'string', 'max:10', Rule::unique('companies', 'employee_no_prefix')->ignore($this->editingId)],
            'formPadding' => ['required', 'integer', 'min:1', 'max:10'],
            'formEpf' => ['nullable', 'string', 'max:30'],
            'formEtf' => ['nullable', 'string', 'max:30'],
            'formTin' => ['nullable', 'string', 'max:30'],
            'formIsActive' => ['boolean'],
        ]);

        $payload = [
            'code' => $validated['formCode'],
            'name' => $validated['formName'],
            'employee_no_prefix' => $validated['formPrefix'],
            'employee_no_padding' => $validated['formPadding'],
            'epf_employer_no' => $validated['formEpf'] !== '' ? $validated['formEpf'] : null,
            'etf_employer_no' => $validated['formEtf'] !== '' ? $validated['formEtf'] : null,
            'tin' => $validated['formTin'] !== '' ? $validated['formTin'] : null,
            'is_active' => $validated['formIsActive'],
        ];

        if ($this->editingId === null) {
            $companies->create($payload);
        } else {
            $company = Company::query()->findOrFail($this->editingId);
            $companies->update($company, $payload);
        }

        $this->closeForm();
    }

    public function toggleActive(int $companyId, CompanyService $companies): void
    {
        $this->authorize('org.manage');
        $company = Company::query()->findOrFail($companyId);

        if ($company->is_active) {
            $companies->deactivate($company);
        } else {
            $companies->activate($company);
        }
    }

    public function render(): View
    {
        $rows = Company::query()
            ->when($this->search !== '', function ($query) {
                $query->where(function ($inner) {
                    $inner->where('code', 'like', '%'.$this->search.'%')
                        ->orWhere('name', 'like', '%'.$this->search.'%')
                        ->orWhere('employee_no_prefix', 'like', '%'.$this->search.'%');
                });
            })
            ->orderBy('code')
            ->get();

        return view('organisation::livewire.companies-index', [
            'companies' => $rows,
        ])->layout('components.layouts.module', [
            'moduleName' => 'Organisation',
            'title' => 'Companies',
            'moduleNav' => OrganisationNav::items('companies'),
        ]);
    }
}
