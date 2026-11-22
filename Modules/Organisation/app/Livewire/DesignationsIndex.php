<?php

namespace Modules\Organisation\Livewire;

use App\Exceptions\Domain\DomainException;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;
use Modules\Organisation\Models\Designation;
use Modules\Organisation\Services\DesignationService;
use Modules\Organisation\Support\OrganisationNav;

class DesignationsIndex extends Component
{
    public string $search = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $code = '';

    public string $name = '';

    public function openCreate(): void
    {
        $this->authorize('org.manage');
        $this->editingId = null;
        $this->code = '';
        $this->name = '';
        $this->showForm = true;
    }

    public function openEdit(int $designationId): void
    {
        $this->authorize('org.manage');
        $designation = Designation::query()->findOrFail($designationId);
        $this->editingId = $designation->id;
        $this->code = $designation->code;
        $this->name = $designation->name;
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->editingId = null;
        $this->code = '';
        $this->name = '';
    }

    public function save(DesignationService $designations): void
    {
        $this->authorize('org.manage');

        $validated = $this->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('designations', 'code')->ignore($this->editingId)],
            'name' => ['required', 'string', 'max:150'],
        ]);

        try {
            if ($this->editingId === null) {
                $designations->create($validated);
            } else {
                $designation = Designation::query()->findOrFail($this->editingId);
                $designations->update($designation, $validated);
            }
        } catch (DomainException $e) {
            $this->addError('name', $e->getMessage());

            return;
        }

        $this->closeForm();
    }

    public function toggleActive(int $designationId): void
    {
        $this->authorize('org.manage');
        $designation = Designation::query()->findOrFail($designationId);
        $designation->update(['is_active' => ! $designation->is_active]);
    }

    public function render(): View
    {
        $this->authorize('org.view');

        $rows = Designation::query()
            ->when($this->search !== '', function ($query) {
                $query->where(function ($inner) {
                    $inner->where('code', 'like', '%'.$this->search.'%')
                        ->orWhere('name', 'like', '%'.$this->search.'%');
                });
            })
            ->orderBy('code')
            ->get();

        return view('organisation::livewire.designations-index', [
            'designations' => $rows,
        ])->layout('components.layouts.module', [
            'moduleName' => 'Organisation',
            'title' => 'Designations',
            'moduleNav' => OrganisationNav::items('designations'),
        ]);
    }
}
