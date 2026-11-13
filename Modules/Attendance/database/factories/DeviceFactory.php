<?php

namespace Modules\Attendance\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Attendance\Models\Device;
use Modules\Organisation\Models\Location;

/**
 * @extends Factory<Device>
 */
class DeviceFactory extends Factory
{
    protected $model = Device::class;

    public function definition(): array
    {
        return [
            'serial_no' => strtoupper(fake()->unique()->bothify('GED###########')),
            'name' => fake()->words(2, true),
            'location_id' => Location::factory(),
            'device_group' => 'NIT',
            'ip_address' => fake()->optional()->ipv4(),
            'api_token_hash' => hash('sha256', fake()->unique()->sha1()),
            'is_active' => true,
            'last_seen_at' => null,
        ];
    }
}
