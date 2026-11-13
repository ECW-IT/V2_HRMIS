<?php

namespace Modules\Organisation\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Organisation\Exceptions\DuplicateName;
use Modules\Organisation\Services\JobService;
use Tests\TestCase;

class JobServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_name_key_normalises_case_and_spaces(): void
    {
        $this->assertSame('cnc machine operator', JobService::nameKey('  CNC   Machine Operator '));
    }

    public function test_job_name_must_be_unique_ignoring_case_and_spaces(): void
    {
        app(JobService::class)->create(['code' => 'DRV', 'name' => 'Driver']);
        $this->expectException(DuplicateName::class);
        app(JobService::class)->create(['code' => 'DRV2', 'name' => ' driver ']);
    }

    public function test_renaming_a_job_to_an_existing_name_is_refused(): void
    {
        $svc = app(JobService::class);
        $svc->create(['code' => 'DRV', 'name' => 'Driver']);
        $b = $svc->create(['code' => 'SUP', 'name' => 'Supervisor']);
        $this->expectException(DuplicateName::class);
        $svc->update($b, ['name' => 'DRIVER']);
    }
}
