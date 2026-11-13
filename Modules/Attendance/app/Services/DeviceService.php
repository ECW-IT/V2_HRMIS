<?php

namespace Modules\Attendance\Services;

use Illuminate\Support\Str;
use Modules\Attendance\Models\Device;

final class DeviceService
{
    /** Returns [Device, plainToken]. */
    public function register(array $data): array
    {
        $plainToken = Str::random(40);

        $device = Device::query()->create([
            'serial_no' => $data['serial_no'],
            'name' => $data['name'],
            'location_id' => $data['location_id'],
            'device_group' => $data['device_group'],
            'ip_address' => $data['ip_address'] ?? null,
            'api_token_hash' => hash('sha256', $plainToken),
            'is_active' => true,
            'last_seen_at' => null,
        ]);

        return [$device, $plainToken];
    }

    public function rotateToken(Device $device): string
    {
        $plainToken = Str::random(40);
        $device->update(['api_token_hash' => hash('sha256', $plainToken)]);

        return $plainToken;
    }

    public function findByToken(string $plainToken): ?Device
    {
        return Device::query()
            ->where('api_token_hash', hash('sha256', $plainToken))
            ->where('is_active', true)
            ->first();
    }
}
