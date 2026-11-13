<?php

namespace Modules\CoreHr\Livewire;

use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;
use Modules\CoreHr\Models\Bank;
use Modules\CoreHr\Models\BankBranch;
use Modules\CoreHr\Support\SettingsNav;

class BankBranchesIndex extends Component
{
    public string $search = '';

    public ?int $bankFilter = null;

    public bool $showForm = false;

    public ?int $editingId = null;

    public ?int $bank_id = null;

    public string $code = '';

    public string $name = '';

    protected $queryString = [
        'bankFilter' => ['as' => 'bank', 'except' => null],
    ];

    public function updatedBankFilter(mixed $value): void
    {
        $this->bankFilter = ($value === '' || $value === null || $value === 0) ? null : (int) $value;
    }

    public function openCreate(): void
    {
        $this->authorize('settings.manage');
        $this->editingId = null;
        $this->bank_id = $this->bankFilter;
        $this->code = '';
        $this->name = '';
        $this->showForm = true;
    }

    public function openEdit(int $branchId): void
    {
        $this->authorize('settings.manage');
        $branch = BankBranch::query()->findOrFail($branchId);
        $this->editingId = $branch->id;
        $this->bank_id = $branch->bank_id;
        $this->code = $branch->code;
        $this->name = $branch->name;
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->editingId = null;
        $this->bank_id = null;
        $this->code = '';
        $this->name = '';
    }

    public function save(): void
    {
        $this->authorize('settings.manage');

        $validated = $this->validate([
            'bank_id' => ['required', 'integer', 'exists:banks,id'],
            'code' => [
                'required',
                'string',
                'size:3',
                Rule::unique('bank_branches', 'code')
                    ->where(fn ($q) => $q->where('bank_id', $this->bank_id))
                    ->ignore($this->editingId),
            ],
            'name' => ['required', 'string', 'max:150'],
        ]);

        if ($this->editingId === null) {
            BankBranch::query()->create([
                'bank_id' => $validated['bank_id'],
                'code' => $validated['code'],
                'name' => $validated['name'],
                'is_active' => true,
            ]);
        } else {
            BankBranch::query()->findOrFail($this->editingId)->update([
                'bank_id' => $validated['bank_id'],
                'code' => $validated['code'],
                'name' => $validated['name'],
            ]);
        }

        $this->bankFilter = (int) $validated['bank_id'];
        $this->closeForm();
    }

    public function toggleActive(int $branchId): void
    {
        $this->authorize('settings.manage');
        $branch = BankBranch::query()->findOrFail($branchId);
        $branch->update(['is_active' => ! $branch->is_active]);
    }

    public function render(): View
    {
        $this->authorize('settings.view');

        $rows = BankBranch::query()
            ->with('bank')
            ->when($this->bankFilter !== null, fn ($q) => $q->where('bank_id', $this->bankFilter))
            ->when($this->search !== '', function ($query) {
                $query->where(function ($inner) {
                    $inner->where('code', 'like', '%'.$this->search.'%')
                        ->orWhere('name', 'like', '%'.$this->search.'%');
                });
            })
            ->orderBy('bank_id')
            ->orderBy('code')
            ->get();

        return view('corehr::livewire.bank-branches-index', [
            'branches' => $rows,
            'banks' => Bank::query()->orderBy('name')->get(['id', 'code', 'name']),
        ])->layout('components.layouts.module', [
            'moduleName' => 'Settings',
            'title' => 'Bank branches',
            'moduleNav' => SettingsNav::items('bank-branches'),
        ]);
    }
}
