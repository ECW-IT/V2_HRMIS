<?php

namespace Modules\Attendance\Services;

use Illuminate\Support\Facades\DB;
use Modules\Attendance\Models\DeviceGroup;

final class DeviceGroupService
{
    /**
     * @param  array{code: string, name: string}  $data
     */
    public function create(array $data): DeviceGroup
    {
        return DB::transaction(function () use ($data) {
            return DeviceGroup::query()->create([
                'code' => $data['code'],
                'name' => $data['name'],
                'is_active' => true,
            ]);
        });
    }

    /**
     * @param  array{code?: string, name?: string, is_active?: bool}  $data
     */
    public function update(DeviceGroup $group, array $data): DeviceGroup
    {
        return DB::transaction(function () use ($group, $data) {
            $group->update([
                'code' => $data['code'] ?? $group->code,
                'name' => $data['name'] ?? $group->name,
                'is_active' => $data['is_active'] ?? $group->is_active,
            ]);

            return $group->fresh();
        });
    }

    public function deactivate(DeviceGroup $group): DeviceGroup
    {
        $group->update(['is_active' => false]);

        return $group->fresh();
    }

    public function activate(DeviceGroup $group): DeviceGroup
    {
        $group->update(['is_active' => true]);

        return $group->fresh();
    }

    /** Insert any device_group values from devices that are not yet in the master list. */
    public function syncFromDevices(): int
    {
        $existing = DeviceGroup::query()->pluck('code')->all();
        $codes = DB::table('devices')
            ->whereNotNull('device_group')
            ->where('device_group', '!=', '')
            ->distinct()
            ->orderBy('device_group')
            ->pluck('device_group')
            ->all();

        $created = 0;
        foreach ($codes as $code) {
            if (in_array($code, $existing, true)) {
                continue;
            }

            DeviceGroup::query()->create([
                'code' => $code,
                'name' => $code,
                'is_active' => true,
            ]);
            $created++;
        }

        return $created;
    }
}
