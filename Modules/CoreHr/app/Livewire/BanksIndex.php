<?php

namespace Modules\CoreHr\Livewire;

use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;
use Modules\CoreHr\Models\Bank;
use Modules\CoreHr\Support\SettingsNav;

class BanksIndex extends Component
{
    public string $search = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $code = '';

    public string $name = '';

    public function openCreate(): void
    {
        $this->authorize('settings.manage');
        $this->editingId = null;
        $this->code = '';
        $this->name = '';
        $this->showForm = true;
    }

    public function openEdit(int $bankId): void
    {
        $this->authorize('settings.manage');
        $bank = Bank::query()->findOrFail($bankId);
        $this->editingId = $bank->id;
        $this->code = $bank->code;
        $this->name = $bank->name;
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->editingId = null;
        $this->code = '';
        $this->name = '';
    }

    public function save(): void
    {
        $this->authorize('settings.manage');

        $validated = $this->validate([
            'code' => ['required', 'string', 'size:4', Rule::unique('banks', 'code')->ignore($this->editingId)],
            'name' => ['required', 'string', 'max:150'],
        ]);

        if ($this->editingId === null) {
            Bank::query()->create([
                'code' => $validated['code'],
                'name' => $validated['name'],
                'is_active' => true,
            ]);
        } else {
            Bank::query()->findOrFail($this->editingId)->update([
                'code' => $validated['code'],
                'name' => $validated['name'],
            ]);
        }

        $this->closeForm();
    }

    public function toggleActive(int $bankId): void
    {
        $this->authorize('settings.manage');
        $bank = Bank::query()->findOrFail($bankId);
        $bank->update(['is_active' => ! $bank->is_active]);
    }

    public function render(): View
    {
        $this->authorize('settings.view');

        $rows = Bank::query()
            ->when($this->search !== '', function ($query) {
                $query->where(function ($inner) {
                    $inner->where('code', 'like', '%'.$this->search.'%')
                        ->orWhere('name', 'like', '%'.$this->search.'%');
                });
            })
            ->orderBy('code')
            ->get();

        return view('corehr::livewire.banks-index', [
            'banks' => $rows,
        ])->layout('components.layouts.module', [
            'moduleName' => 'Settings',
            'title' => 'Banks',
            'moduleNav' => SettingsNav::items('banks'),
        ]);
    }
}
