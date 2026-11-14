<?php

namespace Modules\Leave\Livewire;

use Livewire\Component;
use Modules\Leave\Models\LeaveType;

class LeaveTypesIndex extends Component
{
    public string $code = '';

    public string $name = '';

    public bool $countsAsCredited = false;

    public bool $allowsHalfDay = true;

    public bool $enforceBalance = true;

    public bool $requiresDocument = false;

    public bool $isActive = true;

    public ?int $editingId = null;

    public function mount(): void
    {
        $this->authorize('leave.view');
    }

    public function save(): void
    {
        $this->authorize('leave.manage');

        $data = $this->validate([
            'code' => 'required|string|max:10',
            'name' => 'required|string|max:100',
            'countsAsCredited' => 'boolean',
            'allowsHalfDay' => 'boolean',
            'enforceBalance' => 'boolean',
            'requiresDocument' => 'boolean',
            'isActive' => 'boolean',
        ]);

        $payload = [
            'code' => $data['code'],
            'name' => $data['name'],
            'counts_as_credited' => $data['countsAsCredited'],
            'allows_half_day' => $data['allowsHalfDay'],
            'enforce_balance' => $data['enforceBalance'],
            'requires_document' => $data['requiresDocument'],
            'is_active' => $data['isActive'],
        ];

        if ($this->editingId) {
            LeaveType::query()->whereKey($this->editingId)->update($payload);
        } else {
            LeaveType::query()->create($payload);
        }

        $this->resetForm();
    }

    public function edit(int $id): void
    {
        $this->authorize('leave.manage');
        $type = LeaveType::query()->findOrFail($id);
        $this->editingId = $type->id;
        $this->code = $type->code;
        $this->name = $type->name;
        $this->countsAsCredited = $type->counts_as_credited;
        $this->allowsHalfDay = $type->allows_half_day;
        $this->enforceBalance = $type->enforce_balance;
        $this->requiresDocument = $type->requires_document;
        $this->isActive = $type->is_active;
    }

    public function resetForm(): void
    {
        $this->editingId = null;
        $this->code = '';
        $this->name = '';
        $this->countsAsCredited = false;
        $this->allowsHalfDay = true;
        $this->enforceBalance = true;
        $this->requiresDocument = false;
        $this->isActive = true;
    }

    public function render()
    {
        return view('leave::livewire.leave-types-index', [
            'types' => LeaveType::query()->orderBy('code')->get(),
        ])->layout('components.layouts.app');
    }
}
