<?php

namespace App\Livewire;

use App\Support\Company\CompanyContext;
use Illuminate\View\View;
use Livewire\Component;
use Modules\Organisation\Models\Company;

class CompanySelector extends Component
{
    public ?int $selectedCompanyId = null;

    public function mount(): void
    {
        CompanyContext::ensureSoleSelected();
        $this->selectedCompanyId = CompanyContext::id();
    }

    public function updatedSelectedCompanyId(mixed $value): void
    {
        $companyId = $value === '' || $value === null ? null : (int) $value;
        CompanyContext::set($companyId);
        $this->selectedCompanyId = $companyId;
        $this->js('window.location.reload()');
    }

    public function render(): View
    {
        CompanyContext::ensureSoleSelected();
        if ($this->selectedCompanyId === null) {
            $this->selectedCompanyId = CompanyContext::id();
        }

        $ids = CompanyContext::permittedIds();
        $companies = Company::query()
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        return view('livewire.company-selector', [
            'companies' => $companies,
            'showAllOption' => $companies->count() > 1,
        ]);
    }
}
