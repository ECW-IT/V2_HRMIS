<?php

namespace Modules\Organisation\Livewire;

use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Component;
use Modules\Organisation\Models\StaffWorkCategory;
use Modules\Organisation\Services\StaffWorkCategoryService;
use Modules\Organisation\Support\OrganisationNav;

class StaffWorkCategoriesIndex extends Component
{
    public string $search = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $code = '';

    public string $name = '';

    public bool $is_active = true;

    public function openCreate(): void
    {
        $this->authorize('org.manage');
        $this->editingId = null;
        $this->resetForm();
        $this->showForm = true;
    }

    public function openEdit(int $categoryId): void
    {
        $this->authorize('org.manage');
        $category = StaffWorkCategory::query()->findOrFail($categoryId);
        $this->editingId = $category->id;
        $this->code = $category->code;
        $this->name = $category->name;
        $this->is_active = (bool) $category->is_active;
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
        $this->is_active = true;
    }

    public function save(StaffWorkCategoryService $service): void
    {
        $this->authorize('org.manage');

        $validated = $this->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('staff_work_categories', 'code')->ignore($this->editingId)],
            'name' => ['required', 'string', 'max:100'],
            'is_active' => ['boolean'],
        ]);

        try {
            if ($this->editingId === null) {
                $service->create($validated);
            } else {
                $service->update(StaffWorkCategory::query()->findOrFail($this->editingId), $validated);
            }
        } catch (ValidationException $e) {
            throw $e;
        }

        $this->closeForm();
    }

    public function deactivate(int $categoryId, StaffWorkCategoryService $service): void
    {
        $this->authorize('org.manage');
        $service->deactivate(StaffWorkCategory::query()->findOrFail($categoryId));
    }

    public function activate(int $categoryId, StaffWorkCategoryService $service): void
    {
        $this->authorize('org.manage');
        $service->update(StaffWorkCategory::query()->findOrFail($categoryId), ['is_active' => true]);
    }

    public function render(): View
    {
        $this->authorize('org.view');

        $rows = StaffWorkCategory::query()
            ->when($this->search !== '', function ($query) {
                $query->where(function ($inner) {
                    $inner->where('code', 'like', '%'.$this->search.'%')
                        ->orWhere('name', 'like', '%'.$this->search.'%');
                });
            })
            ->orderBy('name')
            ->get();

        return view('organisation::livewire.staff-work-categories-index', [
            'categories' => $rows,
        ])->layout('components.layouts.module', [
            'moduleName' => 'Organisation',
            'title' => 'Office / shop categories',
            'moduleNav' => OrganisationNav::items('staff-work-categories'),
        ]);
    }
}
