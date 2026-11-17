<?php

namespace Modules\Organisation\Livewire;

use App\Exceptions\Domain\DomainException;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;
use Modules\Organisation\Models\EmployeeCategory;
use Modules\Organisation\Services\EmployeeCategoryService;
use Modules\Organisation\Support\OrganisationNav;

class EmployeeCategoriesIndex extends Component
{
    public string $search = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $code = '';

    public string $name = '';

    public string $level = '';

    public function openCreate(): void
    {
        $this->authorize('org.manage');
        $this->editingId = null;
        $this->resetFormFields();
        $this->showForm = true;
    }

    public function openEdit(int $categoryId): void
    {
        $this->authorize('org.manage');
        $category = EmployeeCategory::query()->findOrFail($categoryId);
        $this->editingId = $category->id;
        $this->code = $category->code;
        $this->name = $category->name;
        $this->level = (string) $category->level;
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->editingId = null;
        $this->resetFormFields();
    }

    public function save(EmployeeCategoryService $categories): void
    {
        $this->authorize('org.manage');

        $validated = $this->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('employee_categories', 'code')->ignore($this->editingId)],
            'name' => ['required', 'string', 'max:150'],
            'level' => ['required', 'integer', 'min:1', Rule::unique('employee_categories', 'level')->ignore($this->editingId)],
        ]);

        try {
            if ($this->editingId === null) {
                $categories->create($validated);
            } else {
                $categories->update(EmployeeCategory::query()->findOrFail($this->editingId), $validated);
            }
        } catch (DomainException $e) {
            $field = str_contains(strtolower($e->getMessage()), 'level') ? 'level' : 'name';
            $this->addError($field, $e->getMessage());

            return;
        }

        $this->closeForm();
    }

    public function toggleActive(int $categoryId): void
    {
        $this->authorize('org.manage');
        $category = EmployeeCategory::query()->findOrFail($categoryId);
        $category->update(['is_active' => ! $category->is_active]);
    }

    public function render(): View
    {
        $this->authorize('org.view');

        $rows = EmployeeCategory::query()
            ->when($this->search !== '', function ($query) {
                $query->where(function ($inner) {
                    $inner->where('code', 'like', '%'.$this->search.'%')
                        ->orWhere('name', 'like', '%'.$this->search.'%');
                });
            })
            ->orderBy('level')
            ->orderBy('code')
            ->get();

        return view('organisation::livewire.employee-categories-index', [
            'categories' => $rows,
        ])->layout('components.layouts.module', [
            'moduleName' => 'Organisation',
            'title' => 'Employee categories',
            'moduleNav' => OrganisationNav::items('categories'),
        ]);
    }

    private function resetFormFields(): void
    {
        $this->resetValidation();
        $this->code = '';
        $this->name = '';
        $this->level = '';
    }
}
