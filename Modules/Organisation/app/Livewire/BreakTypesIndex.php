<?php

namespace Modules\Organisation\Livewire;

use App\Exceptions\Domain\DomainException;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;
use Modules\Organisation\Models\BreakType;
use Modules\Organisation\Services\BreakTypeService;
use Modules\Organisation\Support\OrganisationNav;

class BreakTypesIndex extends Component
{
    public string $search = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public bool $is_paid = false;

    public string $remarks = '';

    public function openCreate(): void
    {
        $this->authorize('org.manage');
        $this->editingId = null;
        $this->name = '';
        $this->is_paid = false;
        $this->remarks = '';
        $this->showForm = true;
    }

    public function openEdit(int $typeId): void
    {
        $this->authorize('org.manage');
        $type = BreakType::query()->findOrFail($typeId);
        $this->editingId = $type->id;
        $this->name = $type->name;
        $this->is_paid = $type->is_paid;
        $this->remarks = (string) $type->remarks;
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->editingId = null;
        $this->name = '';
        $this->is_paid = false;
        $this->remarks = '';
    }

    public function save(BreakTypeService $types): void
    {
        $this->authorize('org.manage');

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('break_types', 'name')->ignore($this->editingId)],
            'is_paid' => ['boolean'],
            'remarks' => ['nullable', 'string'],
        ]);

        $payload = [
            'name' => $validated['name'],
            'is_paid' => (bool) $validated['is_paid'],
            'remarks' => ($validated['remarks'] ?? '') !== '' ? $validated['remarks'] : null,
        ];

        try {
            if ($this->editingId === null) {
                $types->create($payload);
            } else {
                $type = BreakType::query()->findOrFail($this->editingId);
                $types->update($type, $payload);
            }
        } catch (DomainException $e) {
            $this->addError('name', $e->getMessage());

            return;
        }

        $this->closeForm();
    }

    public function toggleActive(int $typeId): void
    {
        $this->authorize('org.manage');
        $type = BreakType::query()->findOrFail($typeId);
        $type->update(['is_active' => ! $type->is_active]);
    }

    public function render(): View
    {
        $this->authorize('org.view');

        $rows = BreakType::query()
            ->when($this->search !== '', function ($query) {
                $query->where('name', 'like', '%'.$this->search.'%');
            })
            ->orderBy('name')
            ->get();

        return view('organisation::livewire.break-types-index', [
            'types' => $rows,
        ])->layout('components.layouts.module', [
            'moduleName' => 'Organisation',
            'title' => 'Break types',
            'moduleNav' => OrganisationNav::items('break-types'),
        ]);
    }
}
