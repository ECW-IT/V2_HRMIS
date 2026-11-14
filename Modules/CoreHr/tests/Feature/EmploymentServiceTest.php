<?php

namespace Modules\CoreHr\Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\CoreHr\Enums\EmploymentStatus;
use Modules\CoreHr\Enums\EmploymentType;
use Modules\CoreHr\Enums\EventType;
use Modules\CoreHr\Enums\ExitReason;
use Modules\CoreHr\Exceptions\ActiveEmploymentExists;
use Modules\CoreHr\Exceptions\EmploymentNotActive;
use Modules\CoreHr\Exceptions\InvalidEmploymentData;
use Modules\CoreHr\Exceptions\OutsideEmploymentPeriod;
use Modules\CoreHr\Models\Employment;
use Modules\CoreHr\Services\EmploymentService;
use Modules\Organisation\Exceptions\OrgUnitInUse;
use Modules\Organisation\Models\Grade;
use Modules\Organisation\Models\OrgUnit;
use Modules\Organisation\Services\EmployeeCategoryService;
use Modules\Organisation\Services\OrgUnitService;
use Tests\Support\HrFixtures;
use Tests\TestCase;

class EmploymentServiceTest extends TestCase
{
    use HrFixtures;
    use RefreshDatabase;

    private EmploymentService $svc;

    protected function setUp(): void
    {
        parent::setUp();

        $this->svc = app(EmploymentService::class);
    }

    public function test_hire_creates_number_first_assignment_and_event(): void
    {
        $c = $this->company(['employee_no_prefix' => 'ECW', 'employee_no_padding' => 3, 'next_employee_seq' => 1]);
        $e = $this->svc->hire($this->person(), $c, $this->emp(['hire_date' => '2026-12-01', 'type' => 'PROBATION']), $this->asg($c));

        $this->assertSame('ECW001', $e->employee_no);
        $this->assertSame(EmploymentStatus::ACTIVE, $e->status);
        $this->assertSame('2027-06-01', $e->confirmation_due_date->toDateString());
        $this->assertSame(1, $e->assignments()->count());
        $this->assertSame('2026-12-01', $e->assignments()->sole()->effective_from->toDateString());
        $this->assertNull($e->assignments()->sole()->effective_to);
        $this->assertSame(EventType::HIRE, $e->events()->sole()->type);
        $this->assertSame('2026-12-01', $e->events()->sole()->event_date->toDateString());
    }

    public function test_hire_stores_employee_category(): void
    {
        $c = $this->company();
        $category = app(EmployeeCategoryService::class)->create(['code' => 'FAC', 'name' => 'Factory']);

        $e = $this->svc->hire(
            $this->person(),
            $c,
            $this->emp(['employee_category_id' => $category->id]),
            $this->asg($c),
        );

        $this->assertSame($category->id, $e->employee_category_id);
        $this->assertSame('Factory', $e->employeeCategory->name);
    }

    public function test_second_hire_in_company_gets_next_number(): void
    {
        $c = $this->company(['employee_no_prefix' => 'ECW', 'next_employee_seq' => 1]);
        $this->hired(c: $c);
        $this->assertSame('ECW002', $this->hired(c: $c)->employee_no);
    }

    public function test_generator_skips_a_number_already_used_by_an_imported_employee(): void
    {
        $c = $this->company(['employee_no_prefix' => 'ECW', 'next_employee_seq' => 1]);
        $this->svc->hire($this->person(), $c, $this->emp(['employee_no' => 'ECW001']), $this->asg($c));
        $this->assertSame('ECW002', $this->hired(c: $c)->employee_no);
    }

    public function test_person_cannot_hold_two_active_employments_even_across_companies(): void
    {
        $p = $this->person();
        $a = $this->company();
        $b = $this->company();
        $this->svc->hire($p, $a, $this->emp(), $this->asg($a));
        $this->expectException(ActiveEmploymentExists::class);
        $this->svc->hire($p, $b, $this->emp(), $this->asg($b));
    }

    public function test_database_also_refuses_two_active_employments(): void
    {
        $e = $this->hired();
        $this->expectException(QueryException::class);
        Employment::factory()->create(['person_id' => $e->person_id, 'status' => 'ACTIVE']);
    }

    public function test_intern_is_never_epf_liable(): void
    {
        $c = $this->company();
        $e = $this->svc->hire($this->person(), $c, $this->emp(['type' => 'INTERN', 'epf_liable' => true, 'epf_no' => '123']), $this->asg($c));
        $this->assertFalse($e->epf_liable);
        $this->assertNull($e->epf_no);
    }

    public function test_epf_liable_without_epf_number_is_refused(): void
    {
        $c = $this->company();
        $this->expectException(InvalidEmploymentData::class);
        $this->svc->hire($this->person(), $c, $this->emp(['epf_no' => null]), $this->asg($c));
    }

    public function test_epf_number_is_unique_within_a_company_but_may_repeat_across_companies(): void
    {
        $a = $this->company();
        $b = $this->company();
        $this->hired(c: $a, emp: ['epf_no' => '500']);
        $this->assertSame('500', $this->hired(c: $b, emp: ['epf_no' => '500'])->epf_no);
        $this->expectException(InvalidEmploymentData::class);
        $this->hired(c: $a, emp: ['epf_no' => '500']);
    }

    public function test_org_unit_of_another_company_is_refused(): void
    {
        $a = $this->company();
        $b = $this->company();
        $this->expectException(InvalidEmploymentData::class);
        $this->svc->hire($this->person(), $a, $this->emp(), $this->asg($b));
    }

    public function test_hire_date_too_far_in_the_future_is_refused(): void
    {
        $this->travelTo('2026-12-01');
        $c = $this->company();
        $this->expectException(InvalidEmploymentData::class);
        $this->svc->hire($this->person(), $c, $this->emp(['hire_date' => '2027-02-01']), $this->asg($c));
    }

    public function test_confirm_turns_probation_into_permanent(): void
    {
        $e = $this->hired('2026-06-01', emp: ['type' => 'PROBATION']);
        $e = $this->svc->confirm($e, '2026-12-01');
        $this->assertSame(EmploymentType::PERMANENT, $e->type);
        $this->assertSame('2026-12-01', $e->confirmed_on->toDateString());
        $this->assertSame(1, $e->events()->where('type', 'CONFIRMATION')->count());
    }

    public function test_confirming_a_permanent_employee_is_refused(): void
    {
        $this->expectException(InvalidEmploymentData::class);
        $this->svc->confirm($this->hired(), '2026-12-01');
    }

    public function test_assignment_change_closes_previous_and_leaves_no_gap(): void
    {
        $e = $this->hired('2026-06-01');
        $g1 = $e->currentAssignment()->grade_id;
        $g2 = Grade::factory()->create()->id;

        $new = $this->svc->changeAssignment($e, '2026-09-01', ['grade_id' => $g2], 'Promotion');

        $this->assertSame($g1, $e->assignmentOn('2026-08-31')->grade_id);
        $this->assertSame($g2, $e->assignmentOn('2026-09-01')->grade_id);
        $this->assertSame('2026-08-31', $e->assignments()->orderBy('effective_from')->first()->effective_to->toDateString());
        $this->assertSame('Promotion', $new->change_reason);
        $this->assertSame($e->assignments()->orderBy('effective_from')->first()->org_unit_id, $new->org_unit_id);
        $ev = $e->events()->where('type', 'ASSIGNMENT_CHANGE')->sole();
        $this->assertSame(['grade_id' => $g1], $ev->details['from']);
        $this->assertSame(['grade_id' => $g2], $ev->details['to']);
    }

    public function test_assignment_change_before_hire_date_is_refused(): void
    {
        $e = $this->hired('2026-06-01');
        $this->expectException(OutsideEmploymentPeriod::class);
        $this->svc->changeAssignment($e, '2026-05-31', ['grade_id' => Grade::factory()->create()->id], 'x');
    }

    public function test_assignment_change_with_no_difference_is_refused(): void
    {
        $e = $this->hired('2026-06-01');
        $this->expectException(InvalidEmploymentData::class);
        $this->svc->changeAssignment($e, '2026-09-01', ['grade_id' => $e->currentAssignment()->grade_id], 'x');
    }

    public function test_manager_must_be_active_in_the_same_company(): void
    {
        $e = $this->hired('2026-06-01');
        $outsider = $this->hired('2026-01-01');
        $this->expectException(InvalidEmploymentData::class);
        $this->svc->changeAssignment($e, '2026-09-01', ['manager_employment_id' => $outsider->id], 'x');
    }

    public function test_exit_sets_status_and_closes_assignment(): void
    {
        $e = $this->hired('2026-01-01');
        $e = $this->svc->exit($e, '2026-10-31', ExitReason::RESIGNATION, 'Personal reasons');

        $this->assertSame(EmploymentStatus::EXITED, $e->status);
        $this->assertSame('2026-10-31', $e->exit_date->toDateString());
        $this->assertSame(ExitReason::RESIGNATION, $e->exit_reason);
        $this->assertSame('2026-10-31', $e->assignments()->sole()->effective_to->toDateString());
        $this->assertNull($e->assignmentOn('2026-11-01'));
        $this->assertSame(1, $e->events()->where('type', 'EXIT')->count());
    }

    public function test_exit_before_hire_date_is_refused(): void
    {
        $this->expectException(OutsideEmploymentPeriod::class);
        $this->svc->exit($this->hired('2026-06-01'), '2026-05-31', ExitReason::RESIGNATION);
    }

    public function test_exit_before_a_later_assignment_change_is_refused(): void
    {
        $e = $this->hired('2026-01-01');
        $this->svc->changeAssignment($e, '2026-09-01', ['grade_id' => Grade::factory()->create()->id], 'x');
        $this->expectException(OutsideEmploymentPeriod::class);
        $this->svc->exit($e, '2026-08-15', ExitReason::RESIGNATION);
    }

    public function test_exiting_twice_is_refused(): void
    {
        $e = $this->svc->exit($this->hired('2026-01-01'), '2026-10-31', ExitReason::RESIGNATION);
        $this->expectException(EmploymentNotActive::class);
        $this->svc->exit($e, '2026-11-30', ExitReason::RESIGNATION);
    }

    public function test_rejoin_links_to_previous_employment_and_gets_a_new_number(): void
    {
        $c = $this->company();
        $p = $this->person();
        $old = $this->svc->exit($this->hired('2024-01-01', $c, p: $p), '2025-06-30', ExitReason::RESIGNATION);

        $new = $this->svc->rejoin($p, $c, $this->emp(['hire_date' => '2026-12-01']), $this->asg($c));

        $this->assertSame($old->id, $new->previous_employment_id);
        $this->assertNotSame($old->employee_no, $new->employee_no);
        $this->assertSame(EventType::REJOIN, $new->events()->sole()->type);
        $this->assertSame(2, Employment::where('person_id', $p->id)->count());
    }

    public function test_suspend_and_reinstate(): void
    {
        $e = $this->svc->suspend($this->hired(), '2026-11-01', 'Inquiry');
        $this->assertSame(EmploymentStatus::SUSPENDED, $e->status);
        $this->assertSame(EmploymentStatus::ACTIVE, $this->svc->reinstate($e, '2026-11-15')->status);
    }

    public function test_org_unit_with_current_assignments_cannot_be_deactivated(): void
    {
        $e = $this->hired();
        $this->expectException(OrgUnitInUse::class);
        app(OrgUnitService::class)->deactivate(OrgUnit::find($e->currentAssignment()->org_unit_id));
    }

    public function test_org_unit_can_be_deactivated_once_everyone_has_exited(): void
    {
        $e = $this->hired('2026-01-01');
        $unit = OrgUnit::find($e->currentAssignment()->org_unit_id);
        $this->travelTo('2026-12-01');
        $this->svc->exit($e, '2026-10-31', ExitReason::RESIGNATION);
        app(OrgUnitService::class)->deactivate($unit);
        $this->assertFalse($unit->fresh()->is_active);
    }

    public function test_hiring_a_person_active_in_a_company_the_user_cannot_see_gives_a_clear_error(): void
    {
        $a = $this->company();
        $b = $this->company();
        $p = $this->person();
        $this->hired('2026-01-01', $b, p: $p);

        $this->loginAs('hr-manager', $a);

        $this->expectException(ActiveEmploymentExists::class);
        $this->expectExceptionMessage('This person is already employed in another company.');
        $this->svc->hire($p, $a, $this->emp(['hire_date' => '2026-12-01']), $this->asg($a));
    }

    public function test_events_record_who_created_them(): void
    {
        $c = $this->company();
        $user = $this->loginAs('hr-manager', $c);
        $e = $this->hired('2026-01-01', $c);
        $this->assertSame($user->id, $e->events()->where('type', EventType::HIRE)->sole()->created_by);

        auth()->logout();
        $c2 = $this->company();
        $consoleHire = $this->svc->hire($this->person(), $c2, $this->emp(['hire_date' => '2026-01-01']), $this->asg($c2));
        $this->assertNull($consoleHire->events()->where('type', EventType::HIRE)->sole()->created_by);
    }

    public function test_suspend_before_hire_date_is_refused(): void
    {
        $e = $this->hired('2026-06-01');
        $this->expectException(OutsideEmploymentPeriod::class);
        $this->svc->suspend($e, '2026-05-01', 'Too early');
    }
}
