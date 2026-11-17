<?php

namespace Modules\Payroll\Livewire;

use App\Exceptions\Domain\DomainException;
use App\Support\Company\CompanyContext;
use Illuminate\View\View;
use Livewire\Component;
use Modules\Payroll\Models\TaxSlab;
use Modules\Payroll\Services\PayRuleService;
use Modules\Payroll\Support\PayrollNav;

class TaxSlabsIndex extends Component
{
    public bool $showForm = false;

    public string $effective_from = '';

    /** @var list<array{width: string, percent: string}> */
    public array $slabs = [];

    public ?string $formError = null;

    public function boot(): void
    {
        CompanyContext::ensureSoleSelected();
    }

    public function openCreate(): void
    {
        $this->authorize('payroll.config');
        $this->effective_from = '';
        $this->slabs = [
            ['width' => '', 'percent' => ''],
            ['width' => '', 'percent' => ''],
        ];
        $this->formError = null;
        $this->showForm = true;
    }

    public function addSlab(): void
    {
        $this->authorize('payroll.config');
        $this->slabs[] = ['width' => '', 'percent' => ''];
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
            'effective_from' => ['required', 'date'],
            'slabs' => ['required', 'array', 'min:1'],
            'slabs.*.percent' => ['required', 'string'],
            'slabs.*.width' => ['nullable', 'string'],
        ]);

        $payload = [];
        foreach ($this->slabs as $slab) {
            $width = trim((string) ($slab['width'] ?? ''));
            $payload[] = [
                'width' => $width === '' ? null : $width,
                'percent' => trim((string) $slab['percent']),
            ];
        }

        try {
            $rules->setTaxTable($this->effective_from, $payload);
        } catch (DomainException $e) {
            $this->formError = $e->getMessage();

            return;
        }

        $this->closeForm();
    }

    public function render(): View
    {
        $this->authorize('payroll.view');

        $rows = TaxSlab::query()->orderByDesc('effective_from')->orderBy('sequence')->get();

        return view('payroll::livewire.tax-slabs-index', [
            'versions' => $rows->groupBy(fn (TaxSlab $slab) => $slab->effective_from->toDateString()),
            'canConfigure' => auth()->user()?->can('payroll.config') ?? false,
        ])->layout('components.layouts.module', [
            'moduleName' => 'Payroll',
            'title' => 'Tax slabs',
            'moduleNav' => PayrollNav::items('tax-slabs'),
        ]);
    }
}
