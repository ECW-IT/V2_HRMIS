<?php

namespace Modules\Attendance\Tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Attendance\Enums\PunchResolution;
use Modules\Attendance\Models\Punch;
use Modules\Attendance\Services\DeviceService;
use Modules\Attendance\Services\EnrolmentService;
use Modules\Attendance\Services\PunchIngestService;
use Modules\Attendance\Services\PunchResolver;
use Modules\Organisation\Models\Location;
use Tests\Support\HrFixtures;
use Tests\TestCase;

class PunchResolverTest extends TestCase
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

    private function ingest(array $punches): void
    {
        app(PunchIngestService::class)->ingest($this->device, $punches);
    }

    public function test_unresolved_punches_attach_when_the_enrolment_is_added(): void
    {
        $this->ingest([$this->p(999, '2026-12-01 08:00:00'), $this->p(999, '2026-12-01 17:05:00')]);
        $e = $this->hired('2026-06-01');

        app(EnrolmentService::class)->enrol($e, 'NIT', 999, '2026-12-01');

        $this->assertSame(2, Punch::where('employment_id', $e->id)->where('resolution', 'RESOLVED')->count());
    }

    public function test_punches_before_the_enrolment_start_stay_unresolved(): void
    {
        $this->ingest([$this->p(999, '2026-11-30 08:00:00'), $this->p(999, '2026-12-01 08:00:00')]);
        app(EnrolmentService::class)->enrol($this->hired('2026-06-01'), 'NIT', 999, '2026-12-01');
        $this->assertSame(1, Punch::where('resolution', 'UNRESOLVED')->count());
    }

    public function test_resolver_is_limited_to_the_device_group(): void
    {
        [$neg] = app(DeviceService::class)->register([
            'serial_no' => 'GED7254500730',
            'name' => 'Negombo',
            'location_id' => Location::factory()->create()->id,
            'device_group' => 'NEG',
        ]);

        app(PunchIngestService::class)->ingest($neg, [$this->p(50, '2026-12-01 08:00:00')]);
        app(EnrolmentService::class)->enrol($this->hired('2026-01-01'), 'NIT', 50, '2026-01-01');

        $this->assertSame(0, app(PunchResolver::class)->resolvePending('NIT'));
        $this->assertSame(1, Punch::where('resolution', PunchResolution::UNRESOLVED)->count());
    }

    public function test_resolved_punches_are_never_reassigned(): void
    {
        $a = $this->hired('2026-01-01');
        app(EnrolmentService::class)->enrol($a, 'NIT', 1, '2026-01-01');
        $this->ingest([$this->p(1, '2026-12-01 08:00:00')]);

        $punch = Punch::first();
        $this->assertSame($a->id, $punch->employment_id);

        $this->assertSame(0, app(PunchResolver::class)->resolvePending());
        $this->assertSame($a->id, $punch->fresh()->employment_id);
    }

    public function test_punch_cannot_be_edited_or_deleted(): void
    {
        $this->ingest([$this->p(1, '2026-12-01 08:00:00')]);
        $punch = Punch::first();
        try {
            $punch->update(['punched_at' => '2026-12-01 07:00:00']);
            $this->fail();
        } catch (\LogicException) {
        }
        try {
            $punch->delete();
            $this->fail();
        } catch (\LogicException) {
        }
        $this->assertSame('2026-12-01 08:00:00', $punch->fresh()->punched_at->format('Y-m-d H:i:s'));
    }
}
