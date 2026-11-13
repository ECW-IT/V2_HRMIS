<?php

namespace Modules\Attendance\Livewire;

use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;
use Modules\Attendance\Models\Device;
use Modules\Attendance\Models\Punch;
use Modules\Attendance\Services\DeviceService;
use Modules\Organisation\Models\Location;

class DevicesIndex extends Component
{
    public bool $showForm = false;

    public string $serial_no = '';

    public string $name = '';

    public ?int $location_id = null;

    public string $device_group = '';

    public string $ip_address = '';

    public ?string $newToken = null;

    public function openRegister(): void
    {
        $this->authorize('attendance.device.manage');
        $this->resetForm();
        $this->showForm = true;
        $this->newToken = null;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->resetForm();
    }

    public function dismissToken(): void
    {
        $this->newToken = null;
    }

    public function register(DeviceService $devices): void
    {
        $this->authorize('attendance.device.manage');

        $validated = $this->validate([
            'serial_no' => ['required', 'string', 'max:50', Rule::unique('devices', 'serial_no')],
            'name' => ['required', 'string', 'max:100'],
            'location_id' => ['required', 'integer', 'exists:locations,id'],
            'device_group' => ['required', 'string', 'max:20'],
            'ip_address' => ['nullable', 'string', 'max:45'],
        ]);

        [, $token] = $devices->register([
            'serial_no' => $validated['serial_no'],
            'name' => $validated['name'],
            'location_id' => $validated['location_id'],
            'device_group' => $validated['device_group'],
            'ip_address' => ($validated['ip_address'] ?? '') !== '' ? $validated['ip_address'] : null,
        ]);

        $this->newToken = $token;
        $this->showForm = false;
        $this->resetForm();
    }

    public function rotateToken(int $deviceId, DeviceService $devices): void
    {
        $this->authorize('attendance.device.manage');
        $device = Device::query()->findOrFail($deviceId);
        $this->newToken = $devices->rotateToken($device);
    }

    public function toggleActive(int $deviceId): void
    {
        $this->authorize('attendance.device.manage');
        $device = Device::query()->findOrFail($deviceId);
        $device->update(['is_active' => ! $device->is_active]);
    }

    public function render(): View
    {
        $this->authorize('attendance.device.manage');

        $today = now(config('app.timezone'))->toDateString();
        $devices = Device::query()->with('location')->orderBy('name')->get()->map(function (Device $device) use ($today) {
            $device->punches_today = Punch::query()
                ->where('device_id', $device->id)
                ->whereDate('punched_at', $today)
                ->count();

            return $device;
        });

        return view('attendance::livewire.devices-index', [
            'devices' => $devices,
            'locations' => Location::query()->where('is_active', true)->orderBy('name')->get(),
        ])->layout('components.layouts.app', [
            'title' => 'Devices',
        ]);
    }

    private function resetForm(): void
    {
        $this->serial_no = '';
        $this->name = '';
        $this->location_id = null;
        $this->device_group = '';
        $this->ip_address = '';
    }
}
