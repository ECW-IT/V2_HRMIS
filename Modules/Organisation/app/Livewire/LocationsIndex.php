<?php

namespace Modules\Organisation\Livewire;

use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;
use Modules\Organisation\Models\Location;
use Modules\Organisation\Support\OrganisationNav;

class LocationsIndex extends Component
{
    public string $search = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $code = '';

    public string $name = '';

    public string $address = '';

    public bool $is_active = true;

    public function openCreate(): void
    {
        $this->authorize('org.manage');
        $this->editingId = null;
        $this->resetForm();
        $this->showForm = true;
    }

    public function openEdit(int $locationId): void
    {
        $this->authorize('org.manage');
        $location = Location::query()->findOrFail($locationId);
        $this->editingId = $location->id;
        $this->code = $location->code;
        $this->name = $location->name;
        $this->address = (string) ($location->address ?? '');
        $this->is_active = (bool) $location->is_active;
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
        $this->address = '';
        $this->is_active = true;
    }

    public function save(): void
    {
        $this->authorize('org.manage');

        $validated = $this->validate([
            'code' => ['required', 'string', 'max:10', Rule::unique('locations', 'code')->ignore($this->editingId)],
            'name' => ['required', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ]);

        $payload = [
            'code' => $validated['code'],
            'name' => $validated['name'],
            'address' => ($validated['address'] ?? '') !== '' ? $validated['address'] : null,
            'is_active' => $validated['is_active'],
        ];

        if ($this->editingId === null) {
            Location::query()->create($payload);
        } else {
            Location::query()->findOrFail($this->editingId)->update($payload);
        }

        $this->closeForm();
    }

    public function toggleActive(int $locationId): void
    {
        $this->authorize('org.manage');
        $location = Location::query()->findOrFail($locationId);
        $location->update(['is_active' => ! $location->is_active]);
    }

    public function render(): View
    {
        $this->authorize('org.view');

        $rows = Location::query()
            ->when($this->search !== '', function ($query) {
                $query->where(function ($inner) {
                    $inner->where('code', 'like', '%'.$this->search.'%')
                        ->orWhere('name', 'like', '%'.$this->search.'%');
                });
            })
            ->orderBy('code')
            ->get();

        return view('organisation::livewire.locations-index', [
            'locations' => $rows,
        ])->layout('components.layouts.module', [
            'moduleName' => 'Organisation',
            'title' => 'Locations',
            'moduleNav' => OrganisationNav::items('locations'),
        ]);
    }
}
