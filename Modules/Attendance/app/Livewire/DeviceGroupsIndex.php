<?php

namespace Modules\Attendance\Livewire;

use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;
use Modules\Attendance\Models\DeviceGroup;
use Modules\Attendance\Services\DeviceGroupService;
use Modules\Attendance\Support\AttendanceNav;

class DeviceGroupsIndex extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $code = '';

    public string $name = '';

    public function openCreate(): void
    {
        $this->authorize('attendance.device.manage');
        $this->editingId = null;
        $this->resetForm();
        $this->showForm = true;
    }

    public function openEdit(int $groupId): void
    {
        $this->authorize('attendance.device.manage');
        $group = DeviceGroup::query()->findOrFail($groupId);
        $this->editingId = $group->id;
        $this->code = $group->code;
        $this->name = $group->name;
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->editingId = null;
        $this->resetForm();
    }

    public function save(DeviceGroupService $groups): void
    {
        $this->authorize('attendance.device.manage');

        $validated = $this->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('device_groups', 'code')->ignore($this->editingId)],
            'name' => ['required', 'string', 'max:100'],
        ]);

        if ($this->editingId === null) {
            $groups->create($validated);
        } else {
            $groups->update(DeviceGroup::query()->findOrFail($this->editingId), $validated);
        }

        $this->closeForm();
    }

    public function toggleActive(int $groupId, DeviceGroupService $groups): void
    {
        $this->authorize('attendance.device.manage');
        $group = DeviceGroup::query()->findOrFail($groupId);

        if ($group->is_active) {
            $groups->deactivate($group);
        } else {
            $groups->activate($group);
        }
    }

    public function render(): View
    {
        $this->authorize('attendance.device.manage');

        return view('attendance::livewire.device-groups-index', [
            'groups' => DeviceGroup::query()->orderBy('code')->get(),
        ])->layout('components.layouts.module', [
            'moduleName' => 'Attendance',
            'title' => 'Device groups',
            'moduleNav' => AttendanceNav::items('device-groups'),
        ]);
    }

    private function resetForm(): void
    {
        $this->code = '';
        $this->name = '';
    }
}
