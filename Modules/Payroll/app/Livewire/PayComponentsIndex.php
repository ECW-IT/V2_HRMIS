<?php

namespace Modules\Payroll\Livewire;

use App\Exceptions\Domain\DomainException;
use App\Support\Company\CompanyContext;
use Illuminate\View\View;
use Livewire\Component;
use Modules\Payroll\Enums\CalcMethod;
use Modules\Payroll\Enums\ComponentKind;
use Modules\Payroll\Enums\FundCode;
use Modules\Payroll\Models\PayComponent;
use Modules\Payroll\Services\PayComponentService;
use Modules\Payroll\Support\PayrollNav;

class PayComponentsIndex extends Component
{
    public string $filterKind = '';

    public string $filterSheet = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $code = '';

    public string $name = '';

    public string $kind = 'EARNING';

    public string $method = 'FIXED';

    public int|string $sheet = 1;

    public bool $epf_liable = false;

    public bool $taxable = false;

    public string $fund = '';

    public string $night_base_hours = '';

    public int|string $display_order = 0;

    public bool $editingIsSystem = false;

    public ?string $formError = null;

    public function boot(): void
    {
        CompanyContext::ensureSoleSelected();
    }

    public function openCreate(): void
    {
        $this->authorize('payroll.config');
        $this->editingId = null;
        $this->editingIsSystem = false;
        $this->resetForm();
        $this->formError = null;
        $this->showForm = true;
    }

    public function openEdit(int $componentId): void
    {
        $this->authorize('payroll.config');
        $component = PayComponent::query()->findOrFail($componentId);
        $this->editingId = $component->id;
        $this->editingIsSystem = (bool) $component->is_system;
        $this->code = $component->code;
        $this->name = $component->name;
        $this->kind = $component->kind instanceof ComponentKind ? $component->kind->value : (string) $component->kind;
        $this->method = $component->method instanceof CalcMethod ? $component->method->value : (string) $component->method;
        $this->sheet = (int) $component->sheet;
        $this->epf_liable = (bool) $component->epf_liable;
        $this->taxable = (bool) $component->taxable;
        $this->fund = $component->fund instanceof FundCode ? $component->fund->value : (string) ($component->fund ?? '');
        $this->night_base_hours = $component->night_base_hours !== null ? (string) $component->night_base_hours : '';
        $this->display_order = (int) $component->display_order;
        $this->formError = null;
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->editingId = null;
        $this->editingIsSystem = false;
        $this->formError = null;
        $this->resetForm();
    }

    public function save(PayComponentService $components): void
    {
        $this->authorize('payroll.config');
        $this->formError = null;

        $validated = $this->validate([
            'code' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:100'],
            'kind' => ['required', 'string'],
            'method' => ['required', 'string'],
            'sheet' => ['required', 'integer', 'in:1,2'],
            'epf_liable' => ['boolean'],
            'taxable' => ['boolean'],
            'fund' => ['nullable', 'string'],
            'night_base_hours' => ['nullable', 'string'],
            'display_order' => ['required', 'integer', 'min:0'],
        ]);

        $payload = [
            'code' => strtoupper($validated['code']),
            'name' => $validated['name'],
            'kind' => $validated['kind'],
            'method' => $validated['method'],
            'sheet' => (int) $validated['sheet'],
            'epf_liable' => (bool) $validated['epf_liable'],
            'taxable' => (bool) $validated['taxable'],
            'fund' => ($validated['fund'] ?? '') !== '' ? $validated['fund'] : null,
            'night_base_hours' => ($validated['night_base_hours'] ?? '') !== '' ? $validated['night_base_hours'] : null,
            'display_order' => (int) $validated['display_order'],
        ];

        try {
            if ($this->editingId === null) {
                $components->create($payload);
            } else {
                $components->update(PayComponent::query()->findOrFail($this->editingId), $payload);
            }
        } catch (DomainException $e) {
            $this->formError = $e->getMessage();

            return;
        }

        $this->closeForm();
    }

    public function deactivate(int $componentId, PayComponentService $components): void
    {
        $this->authorize('payroll.config');
        $this->formError = null;

        try {
            $components->deactivate(PayComponent::query()->findOrFail($componentId));
        } catch (DomainException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    protected function resetForm(): void
    {
        $this->code = '';
        $this->name = '';
        $this->kind = ComponentKind::EARNING->value;
        $this->method = CalcMethod::FIXED->value;
        $this->sheet = 1;
        $this->epf_liable = false;
        $this->taxable = false;
        $this->fund = '';
        $this->night_base_hours = '';
        $this->display_order = 0;
    }

    public function render(): View
    {
        $this->authorize('payroll.view');

        $rows = PayComponent::query()
            ->when($this->filterKind !== '', fn ($q) => $q->where('kind', $this->filterKind))
            ->when($this->filterSheet !== '', fn ($q) => $q->where('sheet', (int) $this->filterSheet))
            ->orderBy('display_order')
            ->orderBy('code')
            ->get();

        return view('payroll::livewire.pay-components-index', [
            'components' => $rows,
            'kinds' => ComponentKind::cases(),
            'methods' => [
                CalcMethod::FIXED,
                CalcMethod::PROPORTIONAL,
                CalcMethod::QUANTITY_RATE,
                CalcMethod::NIGHT_UNITS,
                CalcMethod::ENTRY,
            ],
            'funds' => FundCode::cases(),
            'canConfigure' => auth()->user()?->can('payroll.config') ?? false,
        ])->layout('components.layouts.module', [
            'moduleName' => 'Payroll',
            'title' => 'Pay components',
            'moduleNav' => PayrollNav::items('components'),
        ]);
    }
}
