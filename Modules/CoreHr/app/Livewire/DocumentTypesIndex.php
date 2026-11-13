<?php

namespace Modules\CoreHr\Livewire;

use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;
use Modules\CoreHr\Models\DocumentType;
use Modules\CoreHr\Support\SettingsNav;

class DocumentTypesIndex extends Component
{
    public string $search = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $code = '';

    public string $name = '';

    public bool $requires_expiry = false;

    public bool $is_mandatory = false;

    public function openCreate(): void
    {
        $this->authorize('settings.manage');
        $this->editingId = null;
        $this->code = '';
        $this->name = '';
        $this->requires_expiry = false;
        $this->is_mandatory = false;
        $this->showForm = true;
    }

    public function openEdit(int $typeId): void
    {
        $this->authorize('settings.manage');
        $type = DocumentType::query()->findOrFail($typeId);
        $this->editingId = $type->id;
        $this->code = $type->code;
        $this->name = $type->name;
        $this->requires_expiry = (bool) $type->requires_expiry;
        $this->is_mandatory = (bool) $type->is_mandatory;
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->editingId = null;
        $this->code = '';
        $this->name = '';
        $this->requires_expiry = false;
        $this->is_mandatory = false;
    }

    public function save(): void
    {
        $this->authorize('settings.manage');

        $validated = $this->validate([
            'code' => ['required', 'string', 'max:30', Rule::unique('document_types', 'code')->ignore($this->editingId)],
            'name' => ['required', 'string', 'max:100'],
            'requires_expiry' => ['boolean'],
            'is_mandatory' => ['boolean'],
        ]);

        $payload = [
            'code' => $validated['code'],
            'name' => $validated['name'],
            'requires_expiry' => (bool) $validated['requires_expiry'],
            'is_mandatory' => (bool) $validated['is_mandatory'],
        ];

        if ($this->editingId === null) {
            DocumentType::query()->create($payload + ['is_active' => true]);
        } else {
            DocumentType::query()->findOrFail($this->editingId)->update($payload);
        }

        $this->closeForm();
    }

    public function toggleActive(int $typeId): void
    {
        $this->authorize('settings.manage');
        $type = DocumentType::query()->findOrFail($typeId);
        $type->update(['is_active' => ! $type->is_active]);
    }

    public function render(): View
    {
        $this->authorize('settings.view');

        $rows = DocumentType::query()
            ->when($this->search !== '', function ($query) {
                $query->where(function ($inner) {
                    $inner->where('code', 'like', '%'.$this->search.'%')
                        ->orWhere('name', 'like', '%'.$this->search.'%');
                });
            })
            ->orderBy('code')
            ->get();

        return view('corehr::livewire.document-types-index', [
            'types' => $rows,
        ])->layout('components.layouts.module', [
            'moduleName' => 'Settings',
            'title' => 'Document types',
            'moduleNav' => SettingsNav::items('document-types'),
        ]);
    }
}
