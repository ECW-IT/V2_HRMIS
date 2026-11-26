<?php

namespace Modules\CoreHr\Tests\Feature\Screens;

use App\Models\User;
use Database\Seeders\ApprovalDefinitionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Attendance\Enums\OtMode;
use Modules\Attendance\Services\TimeProfileService;
use Modules\CoreHr\Enums\EmploymentType;
use Modules\CoreHr\Livewire\Tabs\EditEmploymentStep;
use Modules\CoreHr\Services\EmploymentService;
use Modules\Organisation\Models\Designation;
use Modules\Organisation\Models\EmployeeCategory;
use Modules\Organisation\Models\OrgUnit;
use Modules\Workflow\Models\ApprovalRequest;
use Modules\Workflow\Services\ApprovalService;
use Tests\Support\HrFixtures;
use Tests\TestCase;

class EditEmploymentStepTest extends TestCase
{
    use HrFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ApprovalDefinitionSeeder::class);
    }

    public function test_classification_can_be_updated(): void
    {
        $company = $this->company();
        $employment = $this->hired('2026-01-01', $company, ['type' => EmploymentType::PROBATION->value]);
        $category = EmployeeCategory::factory()->create(['name' => 'Factory']);

        $this->loginAs('hr-manager', $company);

        Livewire::test(EditEmploymentStep::class, ['employment' => $employment])
            ->assertSee('Save classification')
            ->assertSee('Save assignment')
            ->set('type', EmploymentType::PERMANENT->value)
            ->set('employeeCategoryId', $category->id)
            ->call('saveClassification')
            ->assertHasNoErrors()
            ->assertSee('Sent for approval');

        $employment->refresh();
        $this->assertSame(EmploymentType::PROBATION, $employment->type);
        $this->assertNull($employment->employee_category_id);

        app(ApprovalService::class)->approve(ApprovalRequest::sole(), auth()->user());

        $employment->refresh();
        $this->assertSame(EmploymentType::PERMANENT, $employment->type);
        $this->assertSame($category->id, $employment->employee_category_id);
    }

    public function test_assignment_change_sends_for_approval_for_hr_manager(): void
    {
        $company = $this->company();
        $employment = $this->hired('2026-06-01', $company);
        $originalGradeId = $employment->currentAssignment()->grade_id;
        $hireOrgUnitId = $employment->currentAssignment()->org_unit_id;
        $designation = Designation::factory()->create();
        $orgUnit = OrgUnit::factory()->create(['company_id' => $company->id]);

        $this->loginAs('hr-manager', $company);

        Livewire::test(EditEmploymentStep::class, ['employment' => $employment])
            ->assertDontSeeHtml('wire:model="gradeId"')
            ->set('effectiveFrom', '2026-09-01')
            ->set('orgUnitId', $orgUnit->id)
            ->set('designationId', $designation->id)
            ->set('reason', 'Promotion')
            ->call('saveAssignment')
            ->assertHasNoErrors()
            ->assertSee('Sent for approval');

        $this->assertSame(1, $employment->assignments()->count());
        $this->assertSame($hireOrgUnitId, $employment->assignmentOn('2026-09-01')->org_unit_id);

        $approver = User::factory()
            ->forCompanies([$company])
            ->withRole('super-admin')
            ->create(['must_change_password' => false]);
        app(ApprovalService::class)->approve(ApprovalRequest::sole(), $approver);

        $assignment = $employment->assignmentOn('2026-09-01');
        $this->assertSame($designation->id, $assignment->designation_id);
        $this->assertSame($orgUnit->id, $assignment->org_unit_id);
        $this->assertSame($originalGradeId, $assignment->grade_id);
    }

    public function test_changing_effective_from_reloads_assignment_fields_for_that_date(): void
    {
        $company = $this->company();
        $employment = $this->hired('2026-06-01', $company);
        $hireOrgUnitId = $employment->assignmentOn('2026-06-01')->org_unit_id;
        $laterOrgUnit = OrgUnit::factory()->create(['company_id' => $company->id]);

        app(EmploymentService::class)->changeAssignment(
            $employment,
            '2026-09-01',
            ['org_unit_id' => $laterOrgUnit->id],
            'Move',
        );

        $this->loginAs('hr-manager', $company);

        Livewire::test(EditEmploymentStep::class, ['employment' => $employment->fresh()])
            ->set('effectiveFrom', '2026-08-15')
            ->assertSet('orgUnitId', $hireOrgUnitId)
            ->set('effectiveFrom', '2026-09-15')
            ->assertSet('orgUnitId', $laterOrgUnit->id);
    }

    public function test_employee_no_epf_and_hire_date_stay_read_only_labels(): void
    {
        $company = $this->company();
        $employment = $this->hired('2026-01-01', $company);

        $this->loginAs('hr-manager', $company);

        Livewire::test(EditEmploymentStep::class, ['employment' => $employment])
            ->assertSee('Employee number, EPF number and hire date cannot be edited here.')
            ->assertDontSee('use Change assignment')
            ->assertSee($employment->employee_no)
            ->assertSee('2026-01-01');
    }

    public function test_ot_mode_can_be_updated_without_changing_shift(): void
    {
        $company = $this->company();
        $employment = $this->profiled('2026-01-01', $company, ['ot_mode' => OtMode::NONE]);
        $shiftId = $employment->timeProfiles()->sole()->shift_id;

        $this->loginAs('hr-manager', $company);

        Livewire::test(EditEmploymentStep::class, ['employment' => $employment])
            ->assertSee('Save OT mode')
            ->set('otMode', OtMode::EVENING->value)
            ->set('otEffectiveFrom', '2026-06-01')
            ->call('saveOtMode')
            ->assertHasNoErrors()
            ->assertSee('OT mode updated.');

        $profiles = app(TimeProfileService::class);
        $before = $profiles->on($employment, '2026-05-31');
        $after = $profiles->on($employment, '2026-06-01');

        $this->assertSame(OtMode::NONE, $before->ot_mode);
        $this->assertSame($shiftId, $before->shift_id);
        $this->assertSame(OtMode::EVENING, $after->ot_mode);
        $this->assertSame($shiftId, $after->shift_id);
        $this->assertSame($before->attendance_mode, $after->attendance_mode);
        $this->assertSame($before->late_deduction, $after->late_deduction);
    }

    public function test_ot_mode_without_a_time_profile_shows_an_error(): void
    {
        $company = $this->company();
        $employment = $this->hired('2026-01-01', $company);

        $this->loginAs('hr-manager', $company);

        Livewire::test(EditEmploymentStep::class, ['employment' => $employment])
            ->set('otEffectiveFrom', '2026-06-01')
            ->set('otMode', OtMode::EVENING->value)
            ->call('saveOtMode')
            ->assertSee('No time profile is in force');

        $this->assertSame(0, $employment->timeProfiles()->count());
    }
}
