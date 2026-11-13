<?php

namespace Modules\Attendance\Tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Modules\Attendance\Models\Punch;
use Modules\Attendance\Models\PunchRejection;
use Modules\Attendance\Services\DeviceService;
use Modules\Attendance\Services\EnrolmentService;
use Modules\CoreHr\Enums\ExitReason;
use Modules\CoreHr\Services\EmploymentService;
use Modules\Organisation\Models\Location;
use Tests\Support\HrFixtures;
use Tests\TestCase;

class PunchIngestApiTest extends TestCase
{
    use HrFixtures;
    use RefreshDatabase;

    private $device;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-12-01 09:00:00');

        [$this->device, $this->token] = app(DeviceService::class)->register([
            'serial_no' => 'GED7243600417',
            'name' => 'Nittambuwa',
            'location_id' => Location::factory()->create()->id,
            'device_group' => 'NIT',
        ]);
    }

    private function send(array $punches, ?string $token = null, ?string $serial = null): TestResponse
    {
        return $this->withToken($token ?? $this->token)->postJson('/api/v1/attendance/punches', [
            'serial_no' => $serial ?? 'GED7243600417',
            'punches' => $punches,
        ]);
    }

    /** @return array{device_user_id: int, punched_at: string, verify_mode: int, state: int} */
    private function p(int $uid, string $at): array
    {
        return [
            'device_user_id' => $uid,
            'punched_at' => $at,
            'verify_mode' => 1,
            'state' => 0,
        ];
    }

    public function test_valid_batch_is_stored_and_resolved(): void
    {
        $e = $this->hired('2026-01-01');
        app(EnrolmentService::class)->enrol($e, 'NIT', 116, '2026-01-01');

        $this->send([$this->p(116, '2026-12-01 08:13:37')])
            ->assertOk()->assertExactJson(['received' => 1, 'inserted' => 1, 'duplicates' => 0, 'unresolved' => 0, 'rejected' => 0]);

        $this->assertDatabaseHas('punches', ['device_id' => $this->device->id, 'device_user_id' => 116, 'punched_at' => '2026-12-01 08:13:37', 'employment_id' => $e->id, 'resolution' => 'RESOLVED']);
        $this->assertSame('2026-12-01 09:00:00', $this->device->fresh()->last_seen_at->format('Y-m-d H:i:s'));
    }

    public function test_same_batch_sent_twice_inserts_once(): void
    {
        $batch = [$this->p(1, '2026-12-01 08:00:00'), $this->p(2, '2026-12-01 08:01:00'), $this->p(3, '2026-12-01 08:02:00')];
        $this->send($batch)->assertJson(['inserted' => 3, 'duplicates' => 0]);
        $this->send($batch)->assertJson(['received' => 3, 'inserted' => 0, 'duplicates' => 3]);
        $this->assertDatabaseCount('punches', 3);
    }

    public function test_duplicate_inside_one_batch_is_stored_once(): void
    {
        $this->send([$this->p(1, '2026-12-01 08:00:00'), $this->p(1, '2026-12-01 08:00:00')])
            ->assertJson(['received' => 2, 'inserted' => 1, 'duplicates' => 1]);
    }

    public function test_unknown_device_user_is_stored_unresolved_not_dropped(): void
    {
        $this->send([$this->p(999, '2026-12-01 08:00:00')])->assertJson(['inserted' => 1, 'unresolved' => 1]);
        $this->assertDatabaseHas('punches', ['device_user_id' => 999, 'employment_id' => null, 'resolution' => 'UNRESOLVED']);
    }

    public function test_punch_resolves_to_the_holder_of_the_id_at_that_time(): void
    {
        $a = $this->hired('2026-01-01');
        $b = $this->hired('2026-11-01');
        $enrol = app(EnrolmentService::class);
        $enrol->enrol($a, 'NIT', 12, '2026-01-01');
        app(EmploymentService::class)->exit($a, '2026-10-31', ExitReason::RESIGNATION);
        $enrol->enrol($b, 'NIT', 12, '2026-11-01');

        $this->send([$this->p(12, '2026-10-30 17:01:00'), $this->p(12, '2026-11-02 07:55:00')]);

        $this->assertSame($a->id, Punch::where('punched_at', '2026-10-30 17:01:00')->sole()->employment_id);
        $this->assertSame($b->id, Punch::where('punched_at', '2026-11-02 07:55:00')->sole()->employment_id);
    }

    public function test_bad_timestamps_are_rejected_and_logged(): void
    {
        $this->send([
            $this->p(1, '0001-08-17 08:00:00'),
            $this->p(1, '2019-12-31 23:59:59'),
            $this->p(1, '2026-12-02 09:00:01'),
            $this->p(1, '2026-12-02 09:00:00'),
        ])->assertJson(['received' => 4, 'inserted' => 1, 'rejected' => 3]);

        $this->assertSame(3, PunchRejection::where('reason', 'BAD_TIMESTAMP')->count());
        $this->assertDatabaseCount('punches', 1);
    }

    public function test_bad_user_ids_are_rejected(): void
    {
        $this->send([$this->p(0, '2026-12-01 08:00:00'), ['device_user_id' => -5, 'punched_at' => '2026-12-01 08:00:00'], ['device_user_id' => 'abc', 'punched_at' => '2026-12-01 08:00:00']])
            ->assertJson(['inserted' => 0, 'rejected' => 3]);
        $this->assertSame(3, PunchRejection::where('reason', 'BAD_USER_ID')->count());
    }

    public function test_malformed_rows_are_rejected_without_stopping_the_batch(): void
    {
        $this->send([['device_user_id' => 7], 'nonsense', ['device_user_id' => 7, 'punched_at' => '01/12/2026 08:00'], $this->p(7, '2026-12-01 08:00:00')])
            ->assertJson(['received' => 4, 'inserted' => 1, 'rejected' => 3]);
        $this->assertSame(3, PunchRejection::where('reason', 'MALFORMED')->count());
    }

    public function test_missing_or_unknown_token_gets_401(): void
    {
        $this->postJson('/api/v1/attendance/punches', ['serial_no' => 'GED7243600417', 'punches' => [$this->p(1, '2026-12-01 08:00:00')]])->assertUnauthorized();
        $this->send([$this->p(1, '2026-12-01 08:00:00')], token: 'wrong')->assertUnauthorized();
        $this->assertDatabaseCount('punches', 0);
    }

    public function test_inactive_device_gets_401(): void
    {
        $this->device->update(['is_active' => false]);
        $this->send([$this->p(1, '2026-12-01 08:00:00')])->assertUnauthorized();
    }

    public function test_serial_not_matching_the_token_gets_422(): void
    {
        $this->send([$this->p(1, '2026-12-01 08:00:00')], serial: 'OTHER123')->assertStatus(422);
        $this->assertDatabaseCount('punches', 0);
    }

    public function test_empty_or_oversized_batch_gets_422(): void
    {
        $this->send([])->assertStatus(422);
        $this->send(array_map(fn ($i) => $this->p($i, '2026-12-01 08:00:00'), range(1, 1001)))->assertStatus(422);
        $this->send(array_map(fn ($i) => $this->p($i, '2026-12-01 08:00:00'), range(1, 1000)))->assertOk()->assertJson(['inserted' => 1000]);
    }

    public function test_rotated_token_replaces_the_old_one(): void
    {
        $new = app(DeviceService::class)->rotateToken($this->device);
        $this->send([$this->p(1, '2026-12-01 08:00:00')])->assertUnauthorized();
        $this->send([$this->p(1, '2026-12-01 08:00:00')], token: $new)->assertOk();
    }

    public function test_plain_token_is_never_stored(): void
    {
        $this->assertSame(hash('sha256', $this->token), $this->device->fresh()->api_token_hash);
        $this->assertDatabaseMissing('devices', ['api_token_hash' => $this->token]);
    }

    public function test_same_user_id_and_time_on_two_devices_are_two_punches(): void
    {
        [$d2, $t2] = app(DeviceService::class)->register(['serial_no' => 'GED7254500730', 'name' => 'Negombo', 'location_id' => Location::factory()->create()->id, 'device_group' => 'NEG']);
        $this->send([$this->p(5, '2026-12-01 08:00:00')]);
        $this->send([$this->p(5, '2026-12-01 08:00:00')], token: $t2, serial: 'GED7254500730')->assertJson(['inserted' => 1]);
        $this->assertDatabaseCount('punches', 2);
    }
}
