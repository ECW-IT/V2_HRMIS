<?php

namespace Modules\CoreHr\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\CoreHr\Enums\EmploymentStatus;
use Modules\CoreHr\Enums\EmploymentType;
use Modules\CoreHr\Enums\ExitReason;
use Modules\CoreHr\Exceptions\InvalidEmploymentData;
use Modules\CoreHr\Models\Employment;
use Modules\CoreHr\Services\EmploymentService;
use Tests\Support\HrFixtures;
use Tests\TestCase;

class SisterCompanyTransferTest extends TestCase
{
    use HrFixtures;
    use RefreshDatabase;

    private EmploymentService $svc;

    protected function setUp(): void
    {
        parent::setUp();

        $this->svc = app(EmploymentService::class);
    }

    public function test_transfer_creates_linked_second_employment(): void
    {
        $a = $this->company(['employee_no_prefix' => 'ECW']);
        $b = $this->company(['employee_no_prefix' => 'ECN', 'next_employee_seq' => 40]);
        $old = $this->hired('2024-03-01', $a, ['epf_no' => '500', 'type' => 'PERMANENT']);

        $new = $this->svc->transferToSisterCompany($old, $b, '2026-11-30', $this->asg($b), ['epf_no' => '77']);
        $old->refresh();

        $this->assertSame(EmploymentStatus::EXITED, $old->status);
        $this->assertSame('2026-11-30', $old->exit_date->toDateString());
        $this->assertSame(ExitReason::SISTER_COMPANY_TRANSFER, $old->exit_reason);

        $this->assertSame($b->id, $new->company_id);
        $this->assertSame($old->person_id, $new->person_id);
        $this->assertSame($old->id, $new->previous_employment_id);
        $this->assertSame('2026-12-01', $new->hire_date->toDateString());
        $this->assertSame('ECN040', $new->employee_no);
        $this->assertSame('77', $new->epf_no);
        $this->assertSame(EmploymentType::PERMANENT, $new->type);

        $out = $old->events()->where('type', 'SISTER_COMPANY_TRANSFER_OUT')->sole();
        $in = $new->events()->where('type', 'SISTER_COMPANY_TRANSFER_IN')->sole();
        $this->assertSame($new->id, $out->related_employment_id);
        $this->assertSame($old->id, $in->related_employment_id);
        $this->assertSame(0, $old->events()->where('type', 'EXIT')->count());
        $this->assertSame(0, $new->events()->where('type', 'HIRE')->count());
    }

    public function test_failed_transfer_leaves_the_old_employment_untouched(): void
    {
        $a = $this->company();
        $b = $this->company();
        $this->hired(c: $b, emp: ['epf_no' => '77']);
        $old = $this->hired('2024-03-01', $a, ['epf_no' => '500']);

        try {
            $this->svc->transferToSisterCompany($old, $b, '2026-11-30', $this->asg($b), ['epf_no' => '77']);
            $this->fail();
        } catch (InvalidEmploymentData) {
        }

        $old->refresh();
        $this->assertSame(EmploymentStatus::ACTIVE, $old->status);
        $this->assertNull($old->exit_date);
        $this->assertNull($old->assignments()->sole()->effective_to);
        $this->assertSame(1, Employment::where('person_id', $old->person_id)->count());
    }

    public function test_transfer_to_the_same_company_is_refused(): void
    {
        $a = $this->company();
        $old = $this->hired(c: $a);
        $this->expectException(InvalidEmploymentData::class);
        $this->svc->transferToSisterCompany($old, $a, '2026-11-30', $this->asg($a), ['epf_no' => '9']);
    }

    public function test_transferred_intern_stays_non_epf(): void
    {
        $a = $this->company();
        $b = $this->company();
        $old = $this->hired('2026-06-01', $a, ['type' => 'INTERN']);
        $new = $this->svc->transferToSisterCompany($old, $b, '2026-11-30', $this->asg($b));
        $this->assertFalse($new->epf_liable);
        $this->assertSame(EmploymentType::INTERN, $new->type);
    }
}
