<?php

namespace Modules\Attendance\Tests\Feature;

use App\Models\User;
use App\Support\Company\CompanyContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Attendance\Livewire\PunchesIndex;
use Modules\Attendance\Models\Device;
use Modules\Attendance\Services\DeviceService;
use Modules\Attendance\Services\EnrolmentService;
use Modules\Attendance\Services\PunchIngestService;
use Modules\Organisation\Models\Location;
use Spatie\Permission\Models\Permission;
use Tests\Support\HrFixtures;
use Tests\TestCase;

class PunchListScreenTest extends TestCase
{
    use HrFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-12-01 09:00:00');
        Permission::findOrCreate('attendance.punch.view');
    }

    public function test_punches_screen_needs_permission(): void
    {
        $this->get(route('attendance.punches.index'))->assertRedirect();

        $user = User::factory()->create(['must_change_password' => false]);
        $this->actingAs($user)->get(route('attendance.punches.index'))->assertForbidden();

        $allowed = User::factory()->withPermissions('attendance.punch.view')->create(['must_change_password' => false]);
        $this->actingAs($allowed)->get(route('attendance.punches.index'))->assertOk();
    }

    public function test_resolved_punch_for_a_permitted_company_is_listed(): void
    {
        $company = $this->company();
        $person = $this->person(['name_with_initials' => 'A. Allowed']);
        $employment = $this->hired('2026-01-01', $company, [], $person);
        app(EnrolmentService::class)->enrol($employment, 'NIT', 116, '2026-01-01');

        $device = $this->terminal('GED7243600417', 'Nittambuwa', 'NIT');
        app(PunchIngestService::class)->ingest($device, [
            $this->punch(116, '2026-12-01 08:13:37'),
        ]);

        $user = User::factory()
            ->forCompanies([$company])
            ->withPermissions('attendance.punch.view')
            ->create(['must_change_password' => false]);
        $this->actingAs($user);
        CompanyContext::set($company->id);

        Livewire::actingAs($user)
            ->test(PunchesIndex::class)
            ->assertSee($employment->employee_no)
            ->assertSee('A. Allowed')
            ->assertSee('116')
            ->assertSee('08:13:37')
            ->assertSee('Nittambuwa')
            ->assertSee('Resolved');
    }

    public function test_punch_for_another_company_is_hidden(): void
    {
        $allowed = $this->company();
        $other = $this->company();
        $visible = $this->hired('2026-01-01', $allowed, [], $this->person(['name_with_initials' => 'A. Allowed']));
        $hidden = $this->hired('2026-01-01', $other, [], $this->person(['name_with_initials' => 'B. Hidden']));
        app(EnrolmentService::class)->enrol($visible, 'NIT', 116, '2026-01-01');
        app(EnrolmentService::class)->enrol($hidden, 'NEG', 220, '2026-01-01');

        app(PunchIngestService::class)->ingest($this->terminal('GED7243600417', 'Nittambuwa', 'NIT'), [
            $this->punch(116, '2026-12-01 08:13:37'),
        ]);
        app(PunchIngestService::class)->ingest($this->terminal('GED7254500730', 'Negombo', 'NEG'), [
            $this->punch(220, '2026-12-01 07:01:02'),
        ]);

        $user = User::factory()
            ->forCompanies([$allowed])
            ->withPermissions('attendance.punch.view')
            ->create(['must_change_password' => false]);
        $this->actingAs($user);
        CompanyContext::set($allowed->id);

        Livewire::actingAs($user)
            ->test(PunchesIndex::class)
            ->assertSee($visible->employee_no)
            ->assertSee('A. Allowed')
            ->assertDontSee($hidden->employee_no)
            ->assertDontSee('B. Hidden')
            ->assertDontSee('07:01:02');
    }

    public function test_unresolved_punch_is_listed_without_an_employee(): void
    {
        $company = $this->company();
        app(PunchIngestService::class)->ingest($this->terminal('GED7243600417', 'Nittambuwa', 'NIT'), [
            $this->punch(999, '2026-12-01 08:00:00'),
        ]);

        $user = User::factory()
            ->forCompanies([$company])
            ->withPermissions('attendance.punch.view')
            ->create(['must_change_password' => false]);
        $this->actingAs($user);
        CompanyContext::set($company->id);

        Livewire::actingAs($user)
            ->test(PunchesIndex::class)
            ->assertSee('999')
            ->assertSee('08:00:00')
            ->assertSeeHtml('>Unresolved</td>')
            ->assertDontSeeHtml('>Resolved</td>');
    }

    public function test_punch_outside_the_date_range_is_absent(): void
    {
        $company = $this->company();
        $employment = $this->hired('2026-01-01', $company);
        app(EnrolmentService::class)->enrol($employment, 'NIT', 116, '2026-01-01');
        app(PunchIngestService::class)->ingest($this->terminal('GED7243600417', 'Nittambuwa', 'NIT'), [
            $this->punch(116, '2026-11-01 06:15:00'),
            $this->punch(116, '2026-12-01 08:13:37'),
        ]);

        $user = User::factory()
            ->forCompanies([$company])
            ->withPermissions('attendance.punch.view')
            ->create(['must_change_password' => false]);
        $this->actingAs($user);
        CompanyContext::set($company->id);

        Livewire::actingAs($user)
            ->test(PunchesIndex::class)
            ->assertSee('08:13:37')
            ->assertDontSee('2026-11-01')
            ->assertDontSee('06:15:00');
    }

    private function terminal(string $serial, string $name, string $group): Device
    {
        [$device] = app(DeviceService::class)->register([
            'serial_no' => $serial,
            'name' => $name,
            'location_id' => Location::factory()->create()->id,
            'device_group' => $group,
        ]);

        return $device;
    }

    /** @return array{device_user_id: int, punched_at: string, verify_mode: int, state: int} */
    private function punch(int $deviceUserId, string $punchedAt): array
    {
        return [
            'device_user_id' => $deviceUserId,
            'punched_at' => $punchedAt,
            'verify_mode' => 1,
            'state' => 0,
        ];
    }
}
