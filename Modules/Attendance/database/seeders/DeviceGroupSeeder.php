<?php

namespace Modules\Attendance\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Attendance\Services\DeviceGroupService;

class DeviceGroupSeeder extends Seeder
{
    public function run(): void
    {
        app(DeviceGroupService::class)->syncFromDevices();
    }
}
