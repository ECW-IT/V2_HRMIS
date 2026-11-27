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

    public bool $earlyOt = false;

    public bool $eveningOt = false;

    public bool $lateDeduction = false;

    /** @var list<array{name: string, start_time: string, end_time: string, is_paid: bool}> */
    public array $breaks = [];

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
        $this->earlyOt = (bool) $category->early_ot;
        $this->eveningOt = (bool) $category->evening_ot;
        $this->lateDeduction = (bool) $category->late_deduction;
        $this->breaks = $category->breaks->map(fn ($break) => [
            'name' => $break->name,
            'start_time' => substr((string) $break->start_time, 0, 5),
            'end_time' => substr((string) $break->end_time, 0, 5),
            'is_paid' => (bool) $break->is_paid,
        ])->all();
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
            'earlyOt' => ['boolean'],
            'eveningOt' => ['boolean'],
            'lateDeduction' => ['boolean'],
            'breaks' => ['array'],
        ]);

        $breaks = $this->filledBreaks();
        if ($breaks === null) {
            return;
        }

        $payload = [
            'code' => $validated['code'],
            'name' => $validated['name'],
            'level' => (int) $validated['level'],
            'early_ot' => $validated['earlyOt'],
            'evening_ot' => $validated['eveningOt'],
            'late_deduction' => $validated['lateDeduction'],
            'breaks' => $breaks,
        ];

        try {
            if ($this->editingId === null) {
                $categories->create($payload);
            } else {
                $categories->update(EmployeeCategory::query()->findOrFail($this->editingId), $payload);
            }
        } catch (DomainException $e) {
            $field = str_contains(strtolower($e->getMessage()), 'level')
                ? 'level'
                : (str_contains(strtolower($e->getMessage()), 'break') ? 'breaks' : 'name');
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
            ->with('breaks')
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
        $this->earlyOt = false;
        $this->eveningOt = false;
        $this->lateDeduction = false;
        $this->breaks = [];
    }

    public function addBreak(): void
    {
        $this->breaks[] = ['name' => '', 'start_time' => '', 'end_time' => '', 'is_paid' => false];
    }

    public function removeBreak(int $index): void
    {
        unset($this->breaks[$index]);
        $this->breaks = array_values($this->breaks);
    }

    /** @return list<array{name: string, start_time: string, end_time: string, is_paid: bool}>|null */
    private function filledBreaks(): ?array
    {
        $rows = [];

        foreach ($this->breaks as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            $start = trim((string) ($row['start_time'] ?? ''));
            $end = trim((string) ($row['end_time'] ?? ''));

            if ($name === '' && $start === '' && $end === '') {
                continue;
            }

            if ($name === '' || $start === '' || $end === '') {
                $this->addError('breaks', 'Each break needs a name, start, and end.');

                return null;
            }

            $rows[] = [
                'name' => $name,
                'start_time' => $start,
                'end_time' => $end,
                'is_paid' => (bool) ($row['is_paid'] ?? false),
            ];
        }

        return $rows;
    }
}
