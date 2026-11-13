<?php

namespace Modules\CoreHr\Tests\Feature\Screens;

use App\Models\User;
use Database\Seeders\ApprovalDefinitionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Modules\CoreHr\Database\Seeders\DocumentTypeSeeder;
use Modules\CoreHr\Livewire\EmployeeProfile;
use Modules\CoreHr\Livewire\Tabs\Personal;
use Modules\CoreHr\Models\EmployeeDocument;
use Modules\CoreHr\Services\BankAccountService;
use Modules\CoreHr\Services\EmploymentRequestService;
use Modules\CoreHr\Services\EmploymentService;
use Modules\Organisation\Models\Grade;
use Tests\Support\HrFixtures;
use Tests\TestCase;

class EmployeeProfileTest extends TestCase
{
    use HrFixtures;
    use RefreshDatabase;

    public function test_profile_of_another_company_returns_404(): void
    {
        $mine = $this->company();
        $theirs = $this->hired(c: $this->company());
        $this->loginAs('hr-officer', $mine);

        $this->get(route('employees.show', $theirs))->assertNotFound();
    }

    public function test_profile_needs_employee_view(): void
    {
        $e = $this->hired();
        $user = User::factory()->forCompanies([$e->company])->create(['must_change_password' => false]);

        $this->actingAs($user)->get(route('employees.show', $e))->assertForbidden();
    }

    public function test_profile_shows_header_and_summary(): void
    {
        $e = $this->hired();
        $assignment = $e->currentAssignment();
        $this->loginAs('hr-officer', $e->company);

        $this->get(route('employees.show', $e))
            ->assertOk()
            ->assertSee($e->employee_no)
            ->assertSee($e->person->name_with_initials)
            ->assertSee($assignment->job->name)
            ->assertSee($assignment->grade->name)
            ->assertSee($e->person->nic);
    }

    public function test_profile_shows_the_flash_message(): void
    {
        $e = $this->hired();
        $this->loginAs('hr-officer', $e->company);

        $this->withSession(['success' => 'Hired as ECW412.'])
            ->get(route('employees.show', $e))
            ->assertSee('Hired as ECW412.');
    }

    public function test_bank_tab_is_hidden_without_permission(): void
    {
        $e = $this->hired();
        app(BankAccountService::class)->set($e, $this->account());

        $this->loginAs('hr-officer', $e->company);
        Livewire::test(EmployeeProfile::class, ['employment' => $e])
            ->set('tab', 'bank')
            ->assertDontSee('0012345678')
            ->assertDontSee('Bank');

        $this->loginAs('payroll-officer', $e->company);
        Livewire::test(EmployeeProfile::class, ['employment' => $e])
            ->assertSee('Bank')
            ->set('tab', 'bank')
            ->assertSee('0012345678');
    }

    public function test_history_tab_lists_events_newest_first(): void
    {
        $e = $this->hired('2026-01-01');
        app(EmploymentService::class)->changeAssignment($e, '2026-06-01', ['grade_id' => Grade::factory()->create()->id], 'Promotion');

        $this->loginAs('hr-officer', $e->company);

        Livewire::test(EmployeeProfile::class, ['employment' => $e])
            ->set('tab', 'history')
            ->assertSeeInOrder(['Assignment change', 'Hire'])
            ->assertSee('Promotion');
    }

    public function test_other_employments_of_the_person_are_listed_only_when_visible(): void
    {
        $a = $this->company();
        $b = $this->company();
        $old = $this->hired('2024-03-01', $a);
        $new = app(EmploymentService::class)->transferToSisterCompany($old, $b, '2026-11-30', $this->asg($b), ['epf_no' => '77']);

        $this->loginAs('hr-manager', $a, $b);
        Livewire::test(EmployeeProfile::class, ['employment' => $new])->assertSee($old->employee_no);
        Livewire::test(EmployeeProfile::class, ['employment' => $old->fresh()])->assertSee($new->employee_no);

        $this->loginAs('hr-manager', $b);
        Livewire::test(EmployeeProfile::class, ['employment' => $new])->assertDontSee($old->employee_no);
    }

    public function test_pending_requests_bar_is_shown(): void
    {
        $this->seed(ApprovalDefinitionSeeder::class);
        $e = $this->hired('2026-06-01');
        $officer = $this->loginAs('hr-officer', $e->company);
        $grade = Grade::factory()->create();

        app(EmploymentRequestService::class)->request($officer, 'CHANGE_ASSIGNMENT', $e, ['from' => '2026-09-01', 'changes' => ['grade_id' => $grade->id], 'reason' => 'Promotion']);
        app(EmploymentRequestService::class)->request($officer, 'CHANGE_ASSIGNMENT', $e, ['from' => '2026-10-01', 'changes' => ['grade_id' => Grade::factory()->create()->id], 'reason' => 'Promotion']);

        Livewire::test(EmployeeProfile::class, ['employment' => $e])
            ->assertSee('2 changes are waiting for approval.');
    }

    public function test_no_pending_bar_when_nothing_is_waiting(): void
    {
        $e = $this->hired('2026-06-01');
        $this->loginAs('hr-officer', $e->company);

        Livewire::test(EmployeeProfile::class, ['employment' => $e])
            ->assertDontSee('waiting for approval');
    }

    public function test_audit_tab_needs_audit_view(): void
    {
        $e = $this->hired();
        app(BankAccountService::class)->set($e, $this->account());

        $this->loginAs('hr-officer', $e->company);
        Livewire::test(EmployeeProfile::class, ['employment' => $e])
            ->set('tab', 'audit')
            ->assertDontSee('Audit');

        $this->loginAs('auditor', $e->company);
        Livewire::test(EmployeeProfile::class, ['employment' => $e])
            ->assertSee('Audit')
            ->set('tab', 'audit')
            ->assertSee('CREATED')
            ->assertSee('***5678')
            ->assertDontSee('0012345678');
    }

    public function test_documents_tab_lists_missing_mandatory_types(): void
    {
        Storage::fake('private');
        $this->seed(DocumentTypeSeeder::class);
        $e = $this->hired();
        $this->loginAs('hr-officer', $e->company);

        Livewire::test(EmployeeProfile::class, ['employment' => $e])
            ->set('tab', 'documents')
            ->assertSee('Missing')
            ->assertSee('Copy of NIC')
            ->assertSee('Appointment letter');
    }

    public function test_documents_tab_lists_uploaded_documents(): void
    {
        $this->seed(DocumentTypeSeeder::class);
        $e = $this->hired();
        EmployeeDocument::factory()->create([
            'person_id' => $e->person_id,
            'employment_id' => $e->id,
            'original_name' => 'offer-letter.pdf',
        ]);
        $this->loginAs('hr-officer', $e->company);

        Livewire::test(EmployeeProfile::class, ['employment' => $e])
            ->set('tab', 'documents')
            ->assertSee('offer-letter.pdf');
    }

    public function test_attendance_tab_needs_punch_view(): void
    {
        $e = $this->hired();
        $this->loginAs('hr-officer', $e->company);

        Livewire::test(EmployeeProfile::class, ['employment' => $e])
            ->assertSee('Attendance')
            ->set('tab', 'attendance')
            ->assertSee('No punches this month.');

        $user = User::factory()->forCompanies([$e->company])->withPermissions('employee.view')->create(['must_change_password' => false]);
        $this->actingAs($user);
        Livewire::test(EmployeeProfile::class, ['employment' => $e])
            ->assertDontSee('Attendance');
    }

    public function test_readiness_tab_lists_todays_issues(): void
    {
        $e = $this->hired();
        $this->loginAs('hr-officer', $e->company);

        Livewire::test(EmployeeProfile::class, ['employment' => $e])
            ->set('tab', 'readiness')
            ->assertSee('HR004');
    }

    public function test_personal_tab_sends_changes_for_approval_for_an_officer(): void
    {
        $this->seed(ApprovalDefinitionSeeder::class);
        $e = $this->hired();
        $this->loginAs('hr-officer', $e->company);

        Livewire::test(Personal::class, ['employment' => $e])
            ->set('callingName', 'Sunil')
            ->call('save')
            ->assertSee('Sent for approval');

        $this->assertNull($e->person->fresh()->calling_name);
    }

    public function test_personal_tab_saves_directly_for_a_manager(): void
    {
        $e = $this->hired();
        $this->loginAs('hr-manager', $e->company);

        Livewire::test(Personal::class, ['employment' => $e])
            ->set('callingName', 'Sunil')
            ->call('save')
            ->assertSee('Saved.');

        $this->assertSame('Sunil', $e->person->fresh()->calling_name);
    }
}
