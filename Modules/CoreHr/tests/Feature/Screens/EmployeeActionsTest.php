<?php

namespace Modules\CoreHr\Tests\Feature\Screens;

use Database\Seeders\ApprovalDefinitionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Attendance\Services\EnrolmentService;
use Modules\CoreHr\Enums\EmploymentStatus;
use Modules\CoreHr\Enums\EmploymentType;
use Modules\CoreHr\Enums\ExitReason;
use Modules\CoreHr\Livewire\Actions\ChangeAssignment;
use Modules\CoreHr\Livewire\Actions\ConfirmEmployment;
use Modules\CoreHr\Livewire\Actions\Enrol;
use Modules\CoreHr\Livewire\Actions\ExitEmployment;
use Modules\CoreHr\Livewire\Actions\Reinstate;
use Modules\CoreHr\Livewire\Actions\SisterTransfer;
use Modules\CoreHr\Livewire\Actions\Suspend;
use Modules\CoreHr\Livewire\EmployeeProfile;
use Modules\CoreHr\Models\Employment;
use Modules\CoreHr\Services\EmploymentService;
use Modules\Organisation\Models\Grade;
use Modules\Workflow\Models\ApprovalRequest;
use Tests\Support\HrFixtures;
use Tests\TestCase;

class EmployeeActionsTest extends TestCase
{
    use HrFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ApprovalDefinitionSeeder::class);
    }

    public function test_hr_officer_change_creates_pending_request_and_does_not_change_data(): void
    {
        $e = $this->hired('2026-06-01');
        $g1 = $e->currentAssignment()->grade_id;
        $g2 = Grade::factory()->create();
        $this->loginAs('hr-officer', $e->company);

        Livewire::test(ChangeAssignment::class, ['employment' => $e])
            ->set('effectiveFrom', '2026-09-01')->set('gradeId', $g2->id)->set('reason', 'Promotion')->call('save')
            ->assertHasNoErrors()->assertSee('Sent for approval');

        $this->assertSame($g1, $e->assignmentOn('2026-09-01')->grade_id);
        $req = ApprovalRequest::sole();
        $this->assertSame(['grade_id' => $g2->id], $req->payload['changes']);
    }

    public function test_hr_manager_change_is_applied_at_once(): void
    {
        $e = $this->hired('2026-06-01');
        $g2 = Grade::factory()->create();
        $this->loginAs('hr-manager', $e->company);

        Livewire::test(ChangeAssignment::class, ['employment' => $e])
            ->set('effectiveFrom', '2026-09-01')->set('gradeId', $g2->id)->set('reason', 'Promotion')->call('save')
            ->assertHasNoErrors()->assertSee('Saved');

        $this->assertSame($g2->id, $e->assignmentOn('2026-09-01')->grade_id);
        $this->assertSame(0, ApprovalRequest::count());
    }

    public function test_change_without_a_reason_is_a_validation_error(): void
    {
        $e = $this->hired('2026-06-01');
        $g2 = Grade::factory()->create();
        $this->loginAs('hr-manager', $e->company);

        Livewire::test(ChangeAssignment::class, ['employment' => $e])
            ->set('effectiveFrom', '2026-09-01')->set('gradeId', $g2->id)->set('reason', '')->call('save')
            ->assertHasErrors(['reason']);

        $this->assertSame(1, $e->assignments()->count());
    }

    public function test_change_that_changes_nothing_is_refused_and_creates_no_request(): void
    {
        $e = $this->hired('2026-06-01');
        $this->loginAs('hr-officer', $e->company);

        Livewire::test(ChangeAssignment::class, ['employment' => $e])
            ->set('effectiveFrom', '2026-09-01')->set('reason', 'Nothing')->call('save')
            ->assertSee('Nothing was changed');

        $this->assertSame(0, ApprovalRequest::count());
    }

    public function test_change_before_the_hire_date_is_a_validation_error(): void
    {
        $e = $this->hired('2026-06-01');
        $this->loginAs('hr-manager', $e->company);

        Livewire::test(ChangeAssignment::class, ['employment' => $e])
            ->set('effectiveFrom', '2026-01-01')->set('gradeId', Grade::factory()->create()->id)->set('reason', 'Promotion')->call('save')
            ->assertHasErrors(['effectiveFrom']);
    }

    public function test_confirm_applies_for_a_manager(): void
    {
        $e = $this->hired('2026-06-01', null, ['type' => 'PROBATION']);
        $this->loginAs('hr-manager', $e->company);

        Livewire::test(ConfirmEmployment::class, ['employment' => $e])
            ->assertSet('confirmedOn', $e->confirmation_due_date->toDateString())
            ->call('save')
            ->assertSee('Saved');

        $this->assertSame(EmploymentType::PERMANENT, $e->fresh()->type);
    }

    public function test_suspend_and_reinstate(): void
    {
        $e = $this->hired('2026-06-01');
        $this->loginAs('hr-manager', $e->company);

        Livewire::test(Suspend::class, ['employment' => $e])
            ->set('from', '2026-10-01')->set('remarks', '')->call('save')
            ->assertHasErrors(['remarks'])
            ->set('remarks', 'Pending inquiry')->call('save')
            ->assertSee('Saved');
        $this->assertSame(EmploymentStatus::SUSPENDED, $e->fresh()->status);

        Livewire::test(Reinstate::class, ['employment' => $e->fresh()])
            ->set('on', '2026-10-15')->call('save')
            ->assertSee('Saved');
        $this->assertSame(EmploymentStatus::ACTIVE, $e->fresh()->status);
    }

    public function test_exit_dialog_does_not_offer_sister_company_transfer_as_a_reason(): void
    {
        $e = $this->hired('2026-06-01');
        $this->loginAs('hr-manager', $e->company);

        Livewire::test(ExitEmployment::class, ['employment' => $e])
            ->assertSee('Resignation')
            ->assertDontSee('SISTER_COMPANY_TRANSFER')
            ->assertDontSee('Sister company transfer')
            ->set('lastWorkingDay', '2026-11-30')->set('reason', 'SISTER_COMPANY_TRANSFER')->call('save')
            ->assertHasErrors(['reason']);

        $this->assertSame(EmploymentStatus::ACTIVE, $e->fresh()->status);
    }

    public function test_exit_by_a_manager_is_applied(): void
    {
        $e = $this->hired('2026-06-01');
        $this->loginAs('hr-manager', $e->company);

        Livewire::test(ExitEmployment::class, ['employment' => $e])
            ->set('lastWorkingDay', '2026-11-30')->set('reason', 'RESIGNATION')->set('remarks', 'Moved abroad')->call('save')
            ->assertHasNoErrors()->assertSee('Saved');

        $e = $e->fresh();
        $this->assertSame(EmploymentStatus::EXITED, $e->status);
        $this->assertSame(ExitReason::RESIGNATION, $e->exit_reason);
    }

    public function test_exit_needs_employee_exit_permission(): void
    {
        $e = $this->hired('2026-06-01');
        $this->loginAs('hr-officer', $e->company);

        Livewire::test(ExitEmployment::class, ['employment' => $e])
            ->set('lastWorkingDay', '2026-11-30')->set('reason', 'RESIGNATION')
            ->call('save')
            ->assertForbidden();

        $this->assertSame(EmploymentStatus::ACTIVE, $e->fresh()->status);
    }

    public function test_sister_transfer_only_offers_other_permitted_companies(): void
    {
        $a = $this->company(['name' => 'Alpha Garments']);
        $b = $this->company(['name' => 'Bravo Knitwear']);
        $c = $this->company(['name' => 'Charlie Textiles']);
        $e = $this->hired('2026-01-01', $a);
        $this->loginAs('hr-manager', $a, $b);

        Livewire::test(SisterTransfer::class, ['employment' => $e])
            ->assertSee('Bravo Knitwear')
            ->assertDontSee('Charlie Textiles')
            ->assertDontSee('Alpha Garments')
            ->assertSee('A new employee number will be issued in the target company.');

        $this->assertNotNull($c);
    }

    public function test_sister_transfer_to_a_company_that_is_not_permitted_is_refused(): void
    {
        $a = $this->company();
        $c = $this->company();
        $e = $this->hired('2026-01-01', $a);
        $this->loginAs('hr-manager', $a);
        $asg = $this->asg($c);

        Livewire::test(SisterTransfer::class, ['employment' => $e])
            ->set('targetCompanyId', $c->id)->set('lastWorkingDay', '2026-11-30')
            ->set('orgUnitId', $asg['org_unit_id'])->set('jobId', $asg['job_id'])->set('gradeId', $asg['grade_id'])->set('locationId', $asg['location_id'])
            ->set('epfNo', '77')->call('save')
            ->assertHasErrors(['targetCompanyId']);

        $this->assertSame(EmploymentStatus::ACTIVE, $e->fresh()->status);
    }

    public function test_sister_transfer_by_hr_manager_creates_the_new_employment_and_redirects_to_it(): void
    {
        $a = $this->company();
        $b = $this->company();
        $e = $this->hired('2024-03-01', $a);
        $asg = $this->asg($b);
        $this->loginAs('hr-manager', $a, $b);

        Livewire::test(SisterTransfer::class, ['employment' => $e])
            ->set('targetCompanyId', $b->id)->set('lastWorkingDay', '2026-11-30')
            ->set('orgUnitId', $asg['org_unit_id'])->set('jobId', $asg['job_id'])->set('gradeId', $asg['grade_id'])->set('locationId', $asg['location_id'])
            ->set('epfNo', '77')->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('employees.show', Employment::query()->where('company_id', $b->id)->sole()));

        $new = Employment::query()->where('company_id', $b->id)->sole();
        $this->assertSame('2026-12-01', $new->hire_date->toDateString());
        $this->assertSame('77', $new->epf_no);
        $this->assertSame($e->id, $new->previous_employment_id);
        $this->assertSame(EmploymentStatus::EXITED, $e->fresh()->status);
    }

    public function test_sister_transfer_requires_an_epf_number_for_epf_liable_employees(): void
    {
        $a = $this->company();
        $b = $this->company();
        $e = $this->hired('2024-03-01', $a);
        $asg = $this->asg($b);
        $this->loginAs('hr-manager', $a, $b);

        Livewire::test(SisterTransfer::class, ['employment' => $e])
            ->set('targetCompanyId', $b->id)->set('lastWorkingDay', '2026-11-30')
            ->set('orgUnitId', $asg['org_unit_id'])->set('jobId', $asg['job_id'])->set('gradeId', $asg['grade_id'])->set('locationId', $asg['location_id'])
            ->call('save')
            ->assertHasErrors(['epfNo']);
    }

    public function test_enrol_adds_a_device_user_id(): void
    {
        $e = $this->hired('2026-01-01');
        $this->loginAs('hr-manager', $e->company);

        Livewire::test(Enrol::class, ['employment' => $e])
            ->set('deviceGroup', 'NIT')->set('deviceUserId', 116)->set('from', '2026-11-01')
            ->call('save')
            ->assertHasNoErrors()->assertSee('Saved');

        $this->assertSame($e->id, app(EnrolmentService::class)->resolve('NIT', 116, '2026-11-02 08:00:00'));
    }

    public function test_enrol_shows_a_message_when_the_id_is_in_use(): void
    {
        $holder = $this->hired('2026-01-01');
        app(EnrolmentService::class)->enrol($holder, 'NIT', 116, '2026-01-01');
        $e = $this->hired('2026-01-01', $holder->company);
        $this->loginAs('hr-manager', $e->company);

        Livewire::test(Enrol::class, ['employment' => $e])
            ->set('deviceGroup', 'NIT')->set('deviceUserId', 116)->set('from', '2026-11-01')
            ->call('save')
            ->assertSee('already held by '.$holder->employee_no);
    }

    public function test_enrol_needs_device_manage_permission(): void
    {
        $e = $this->hired('2026-01-01');
        $this->loginAs('hr-officer', $e->company);

        Livewire::test(Enrol::class, ['employment' => $e])
            ->set('deviceGroup', 'NIT')->set('deviceUserId', 116)->set('from', '2026-11-01')
            ->call('save')
            ->assertForbidden();
    }

    public function test_buttons_follow_status(): void
    {
        $company = $this->company();
        $exited = app(EmploymentService::class)->exit($this->hired('2026-01-01', $company), '2026-06-30', ExitReason::RESIGNATION);
        $probation = $this->hired('2026-06-01', $company, ['type' => 'PROBATION']);
        $permanent = $this->hired('2026-06-01', $company, ['type' => 'PERMANENT']);
        $this->loginAs('hr-manager', $company);

        Livewire::test(EmployeeProfile::class, ['employment' => $exited])
            ->assertDontSee('Change assignment')
            ->assertDontSee('Exit')
            ->assertDontSee('Transfer');

        Livewire::test(EmployeeProfile::class, ['employment' => $probation])
            ->assertSee('Confirm')
            ->assertSee('Change assignment')
            ->assertSee('Transfer to sister company')
            ->assertSee('Exit');

        Livewire::test(EmployeeProfile::class, ['employment' => $permanent])
            ->assertDontSee('Confirm');
    }

    public function test_buttons_follow_permissions(): void
    {
        $e = $this->hired('2026-06-01');
        $this->loginAs('hr-officer', $e->company);

        Livewire::test(EmployeeProfile::class, ['employment' => $e])
            ->assertSee('Change assignment')
            ->assertDontSee('Transfer')
            ->assertDontSee('Suspend');
    }

    public function test_suspended_employee_shows_reinstate_instead_of_suspend(): void
    {
        $e = $this->hired('2026-06-01');
        app(EmploymentService::class)->suspend($e, '2026-10-01', 'Inquiry');
        $this->loginAs('hr-manager', $e->company);

        Livewire::test(EmployeeProfile::class, ['employment' => $e->fresh()])
            ->assertSee('Reinstate')
            ->assertDontSee('Suspend')
            ->assertDontSee('Transfer to sister company');
    }

    public function test_opening_an_action_shows_its_dialog_on_the_profile(): void
    {
        $e = $this->hired('2026-06-01');
        $this->loginAs('hr-manager', $e->company);

        Livewire::test(EmployeeProfile::class, ['employment' => $e])
            ->call('openAction', 'change-assignment')
            ->assertSet('action', 'change-assignment')
            ->assertSee('Effective from')
            ->call('closeAction')
            ->assertSet('action', null);
    }

    public function test_an_officer_cannot_open_the_exit_dialog(): void
    {
        $e = $this->hired('2026-06-01');
        $this->loginAs('hr-officer', $e->company);

        Livewire::test(EmployeeProfile::class, ['employment' => $e])
            ->call('openAction', 'exit')
            ->assertForbidden();
    }
}
