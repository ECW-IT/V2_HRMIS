<?php

namespace Modules\Attendance\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Attendance\Livewire\DeviceGroupsIndex;
use Modules\Attendance\Models\Device;
use Modules\Attendance\Models\DeviceGroup;
use Modules\Attendance\Services\DeviceGroupService;
use Modules\Organisation\Models\Location;
use Tests\TestCase;

class DeviceGroupServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_and_deactivate_device_group(): void
    {
        $service = app(DeviceGroupService::class);
        $group = $service->create(['code' => 'NIT', 'name' => 'Nittambuwa']);

        $this->assertTrue($group->is_active);
        $this->assertDatabaseHas('device_groups', ['code' => 'NIT', 'name' => 'Nittambuwa']);

        $service->deactivate($group);
        $this->assertFalse($group->fresh()->is_active);
    }

    public function test_sync_from_devices_imports_distinct_groups(): void
    {
        $location = Location::factory()->create();
        Device::factory()->create(['location_id' => $location->id, 'device_group' => 'NIT']);
        Device::factory()->create(['location_id' => $location->id, 'device_group' => 'NIT']);
        Device::factory()->create(['location_id' => $location->id, 'device_group' => 'GAM']);

        $created = app(DeviceGroupService::class)->syncFromDevices();

        $this->assertSame(2, $created);
        $this->assertTrue(DeviceGroup::query()->where('code', 'NIT')->exists());
        $this->assertTrue(DeviceGroup::query()->where('code', 'GAM')->exists());
    }

    public function test_device_groups_screen_can_create_a_group(): void
    {
        $user = User::factory()->withPermissions('attendance.device.manage')->create(['must_change_password' => false]);

        Livewire::actingAs($user)
            ->test(DeviceGroupsIndex::class)
            ->call('openCreate')
            ->set('code', 'NIT')
            ->set('name', 'Nittambuwa')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('device_groups', ['code' => 'NIT', 'name' => 'Nittambuwa', 'is_active' => true]);
    }
}
