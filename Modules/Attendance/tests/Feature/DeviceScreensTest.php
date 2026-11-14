<?php

namespace Modules\Attendance\Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\Company\CompanyContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Attendance\Livewire\DevicesIndex;
use Modules\Attendance\Livewire\UnresolvedPunches;
use Modules\Attendance\Models\Device;
use Modules\Attendance\Services\DeviceService;
use Modules\Attendance\Services\PunchIngestService;
use Modules\Organisation\Models\Location;
use Spatie\Permission\Models\Permission;
use Tests\Support\HrFixtures;
use Tests\TestCase;

class DeviceScreensTest extends TestCase
{
    use HrFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-12-01 09:00:00');
        Permission::findOrCreate('attendance.device.manage');
    }

    public function test_devices_screen_needs_permission(): void
    {
        $user = User::factory()->create(['must_change_password' => false]);
        $this->actingAs($user)->get(route('attendance.devices.index'))->assertForbidden();

        $allowed = User::factory()->withPermissions('attendance.device.manage')->create(['must_change_password' => false]);
        $this->actingAs($allowed)->get(route('attendance.devices.index'))->assertOk();
    }

    public function test_registering_a_device_shows_the_token_once(): void
    {
        $user = User::factory()->withPermissions('attendance.device.manage')->create(['must_change_password' => false]);
        $location = Location::factory()->create();

        Livewire::actingAs($user)
            ->test(DevicesIndex::class)
            ->set('serial_no', 'GED7243600417')
            ->set('name', 'Nittambuwa')
            ->set('location_id', $location->id)
            ->set('device_group', 'NIT')
            ->call('register')
            ->assertSet('newToken', fn ($t) => is_string($t) && strlen($t) === 40);

        Livewire::actingAs($user)
            ->test(DevicesIndex::class)
            ->assertSet('newToken', null);
    }

    public function test_duplicate_serial_is_a_validation_error(): void
    {
        $user = User::factory()->withPermissions('attendance.device.manage')->create(['must_change_password' => false]);
        $location = Location::factory()->create();
        app(DeviceService::class)->register([
            'serial_no' => 'GED7243600417',
            'name' => 'One',
            'location_id' => $location->id,
            'device_group' => 'NIT',
        ]);

        Livewire::actingAs($user)
            ->test(DevicesIndex::class)
            ->set('serial_no', 'GED7243600417')
            ->set('name', 'Two')
            ->set('location_id', $location->id)
            ->set('device_group', 'NIT')
            ->call('register')
            ->assertHasErrors(['serial_no']);
    }

    public function test_enrolling_from_the_unresolved_screen_clears_the_group(): void
    {
        $c = $this->company();
        $employment = $this->hired('2026-01-01', $c);
        [$device] = app(DeviceService::class)->register([
            'serial_no' => 'GED7243600417',
            'name' => 'Nittambuwa',
            'location_id' => Location::factory()->create()->id,
            'device_group' => 'NIT',
        ]);

        app(PunchIngestService::class)->ingest($device, [
            ['device_user_id' => 999, 'punched_at' => '2026-12-01 08:00:00', 'verify_mode' => 1, 'state' => 0],
            ['device_user_id' => 999, 'punched_at' => '2026-12-01 12:00:00', 'verify_mode' => 1, 'state' => 0],
            ['device_user_id' => 999, 'punched_at' => '2026-12-01 17:00:00', 'verify_mode' => 1, 'state' => 0],
        ]);

        $user = User::factory()
            ->forCompanies([$c])
            ->withPermissions('attendance.device.manage')
            ->create(['must_change_password' => false]);

        $this->actingAs($user);
        CompanyContext::set($c->id);

        Livewire::actingAs($user)
            ->test(UnresolvedPunches::class)
            ->assertSee('999')
            ->call('enrol', 'NIT', 999, $employment->id, '2026-12-01')
            ->assertDontSee('999');
    }

    public function test_unresolved_screen_only_offers_employees_of_permitted_companies(): void
    {
        $a = $this->company();
        $b = $this->company();
        $inA = $this->hired('2026-01-01', $a);
        $inB = $this->hired('2026-01-01', $b);

        [$device] = app(DeviceService::class)->register([
            'serial_no' => 'GED7243600417',
            'name' => 'Nittambuwa',
            'location_id' => Location::factory()->create()->id,
            'device_group' => 'NIT',
        ]);
        app(PunchIngestService::class)->ingest($device, [
            ['device_user_id' => 50, 'punched_at' => '2026-12-01 08:00:00', 'verify_mode' => 1, 'state' => 0],
        ]);

        $user = User::factory()
            ->forCompanies([$a])
            ->withPermissions('attendance.device.manage')
            ->create(['must_change_password' => false]);

        $this->actingAs($user);
        CompanyContext::set($a->id);

        Livewire::actingAs($user)
            ->test(UnresolvedPunches::class)
            ->assertSee($inA->employee_no)
            ->assertDontSee($inB->employee_no);
    }

    public function test_token_hash_is_never_written_to_the_audit_log(): void
    {
        $location = Location::factory()->create();
        [$device] = app(DeviceService::class)->register([
            'serial_no' => 'GED7243600417',
            'name' => 'Nittambuwa',
            'location_id' => $location->id,
            'device_group' => 'NIT',
        ]);
        app(DeviceService::class)->rotateToken($device);

        $logs = AuditLog::query()
            ->where('auditable_type', Device::class)
            ->get();

        $this->assertNotEmpty($logs);
        foreach ($logs as $log) {
            $this->assertArrayNotHasKey('api_token_hash', $log->old_values ?? []);
            $this->assertArrayNotHasKey('api_token_hash', $log->new_values ?? []);
        }
    }
}
