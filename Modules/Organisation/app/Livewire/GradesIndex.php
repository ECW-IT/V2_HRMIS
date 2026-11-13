<?php

namespace Modules\Organisation\Livewire;

use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;
use Modules\Organisation\Models\Grade;
use Modules\Organisation\Support\OrganisationNav;

class GradesIndex extends Component
{
    public string $search = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $code = '';

    public string $name = '';

    public int|string $rank = '';

    public bool $is_active = true;

    public function openCreate(): void
    {
        $this->authorize('org.manage');
        $this->editingId = null;
        $this->resetForm();
        $this->showForm = true;
    }

    public function openEdit(int $gradeId): void
    {
        $this->authorize('org.manage');
        $grade = Grade::query()->findOrFail($gradeId);
        $this->editingId = $grade->id;
        $this->code = $grade->code;
        $this->name = $grade->name;
        $this->rank = $grade->rank;
        $this->is_active = (bool) $grade->is_active;
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
        $this->code = '';
        $this->name = '';
        $this->rank = '';
        $this->is_active = true;
    }

    public function save(): void
    {
        $this->authorize('org.manage');

        $validated = $this->validate([
            'code' => ['required', 'string', 'max:10', Rule::unique('grades', 'code')->ignore($this->editingId)],
            'name' => ['required', 'string', 'max:100', Rule::unique('grades', 'name')->ignore($this->editingId)],
            'rank' => ['required', 'integer', 'min:1', 'max:65535', Rule::unique('grades', 'rank')->ignore($this->editingId)],
            'is_active' => ['boolean'],
        ]);

        $payload = [
            'code' => $validated['code'],
            'name' => $validated['name'],
            'rank' => $validated['rank'],
            'is_active' => $validated['is_active'],
        ];

        if ($this->editingId === null) {
            Grade::query()->create($payload);
        } else {
            Grade::query()->findOrFail($this->editingId)->update($payload);
        }

        $this->closeForm();
    }

    public function toggleActive(int $gradeId): void
    {
        $this->authorize('org.manage');
        $grade = Grade::query()->findOrFail($gradeId);
        $grade->update(['is_active' => ! $grade->is_active]);
    }

    public function render(): View
    {
        $this->authorize('org.view');

        $rows = Grade::query()
            ->when($this->search !== '', function ($query) {
                $query->where(function ($inner) {
                    $inner->where('code', 'like', '%'.$this->search.'%')
                        ->orWhere('name', 'like', '%'.$this->search.'%');
                });
            })
            ->orderBy('rank')
            ->get();

        return view('organisation::livewire.grades-index', [
            'grades' => $rows,
        ])->layout('components.layouts.module', [
            'moduleName' => 'Organisation',
            'title' => 'Grades',
            'moduleNav' => OrganisationNav::items('grades'),
        ]);
    }
}
