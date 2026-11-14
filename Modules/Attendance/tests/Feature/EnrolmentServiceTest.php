<?php

namespace Modules\Attendance\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Attendance\Exceptions\DeviceUserIdInUse;
use Modules\Attendance\Services\EnrolmentService;
use Modules\CoreHr\Enums\ExitReason;
use Modules\CoreHr\Services\EmploymentService;
use Tests\Support\HrFixtures;
use Tests\TestCase;

class EnrolmentServiceTest extends TestCase
{
    use HrFixtures;
    use RefreshDatabase;

    private EnrolmentService $svc;

    protected function setUp(): void
    {
        parent::setUp();

        $this->svc = app(EnrolmentService::class);
    }

    public function test_enrol_and_resolve(): void
    {
        $e = $this->hired('2026-01-01');
        $this->svc->enrol($e, 'NIT', 116, '2026-01-01');
        $this->assertSame($e->id, $this->svc->resolve('NIT', 116, '2026-03-10 08:13:37'));
        $this->assertNull($this->svc->resolve('NEG', 116, '2026-03-10 08:13:37'));
        $this->assertNull($this->svc->resolve('NIT', 117, '2026-03-10 08:13:37'));
        $this->assertNull($this->svc->resolve('NIT', 116, '2025-12-31 23:59:59'));
    }

    public function test_device_user_id_cannot_be_shared_by_two_active_employees(): void
    {
        $a = $this->hired('2026-01-01');
        $b = $this->hired('2026-01-01');
        $this->svc->enrol($a, 'NIT', 116, '2026-01-01');
        $this->expectException(DeviceUserIdInUse::class);
        $this->svc->enrol($b, 'NIT', 116, '2026-06-01');
    }

    public function test_same_id_in_another_device_group_is_allowed(): void
    {
        $a = $this->hired();
        $b = $this->hired();
        $this->svc->enrol($a, 'NIT', 116, '2026-01-01');
        $this->assertNotNull($this->svc->enrol($b, 'NEG', 116, '2026-01-01'));
    }

    public function test_id_can_be_reused_after_the_previous_holder_exited(): void
    {
        $a = $this->hired('2026-01-01');
        $b = $this->hired('2026-11-01');
        $this->svc->enrol($a, 'NIT', 12, '2026-01-01');
        app(EmploymentService::class)->exit($a, '2026-10-31', ExitReason::RESIGNATION);

        $this->svc->enrol($b, 'NIT', 12, '2026-11-01');

        $this->assertSame($a->id, $this->svc->resolve('NIT', 12, '2026-10-30 17:01:00'));
        $this->assertSame($a->id, $this->svc->resolve('NIT', 12, '2026-10-31 17:01:00'));
        $this->assertSame($b->id, $this->svc->resolve('NIT', 12, '2026-11-01 07:55:00'));
    }

    public function test_reuse_before_the_previous_holder_left_is_refused(): void
    {
        $a = $this->hired('2026-01-01');
        $b = $this->hired('2026-10-01');
        $this->svc->enrol($a, 'NIT', 12, '2026-01-01');
        app(EmploymentService::class)->exit($a, '2026-10-31', ExitReason::RESIGNATION);
        $this->expectException(DeviceUserIdInUse::class);
        $this->svc->enrol($b, 'NIT', 12, '2026-10-15');
    }

    public function test_device_user_id_zero_is_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->svc->enrol($this->hired(), 'NIT', 0, '2026-01-01');
    }

    public function test_new_enrolment_in_same_group_ends_the_old_one(): void
    {
        $e = $this->hired('2026-01-01');
        $old = $this->svc->enrol($e, 'NIT', 116, '2026-01-01');
        $this->svc->enrol($e, 'NIT', 240, '2026-06-01');
        $this->assertSame('2026-05-31', $old->fresh()->valid_to->toDateString());
    }

    public function test_exit_closes_enrolments(): void
    {
        $e = $this->hired('2026-01-01');
        $enrol = $this->svc->enrol($e, 'NIT', 116, '2026-01-01');
        app(EmploymentService::class)->exit($e, '2026-10-31', ExitReason::RESIGNATION);
        $this->assertSame('2026-10-31', $enrol->fresh()->valid_to->toDateString());
    }

    public function test_end_date_cannot_be_before_the_start(): void
    {
        $e = $this->hired('2026-01-01');
        $enrol = $this->svc->enrol($e, 'NIT', 116, '2026-06-01');
        $this->expectException(\InvalidArgumentException::class);
        $this->svc->end($enrol, '2026-05-01');
    }
}
