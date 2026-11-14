<?php

namespace Modules\CoreHr\Tests\Feature\Screens;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Modules\Attendance\Services\EnrolmentService;
use Modules\CoreHr\Enums\ExitReason;
use Modules\CoreHr\Livewire\HireWizard;
use Modules\CoreHr\Models\Employment;
use Modules\CoreHr\Models\Person;
use Modules\CoreHr\Services\BankAccountService;
use Modules\CoreHr\Services\EmploymentService;
use Modules\Organisation\Models\Company;
use Tests\Support\HrFixtures;
use Tests\TestCase;

class HireWizardTest extends TestCase
{
    use HrFixtures;
    use RefreshDatabase;

    /** Walks the wizard from step 1 to step 5 for a new person. */
    private function walkToReview(string $companyId, array $employment = [], bool $withBank = true): Testable
    {
        $a = $this->asg(Company::query()->findOrFail($companyId));

        $component = Livewire::test(HireWizard::class)
            ->set('companyId', $companyId)->set('nic', '853400937V')->call('next')
            ->set('firstName', 'Nimal')->set('lastName', 'Perera')->set('nameWithInitials', 'N. Perera')->set('mobile', '0771234567')
            ->set('addressLine1', '12 Main St')->set('city', 'Nittambuwa')->call('next');

        $shift = $this->shift();

        foreach (array_merge([
            'type' => 'PERMANENT',
            'hireDate' => '2026-12-01',
            'epfNo' => '900',
            'orgUnitId' => $a['org_unit_id'],
            'jobId' => $a['job_id'],
            'gradeId' => $a['grade_id'],
            'locationId' => $a['location_id'],
            'shiftId' => $shift->id,
        ], $employment) as $property => $value) {
            $component->set($property, $value);
        }

        $component->call('next');

        if ($withBank) {
            [$bank, $branch] = $this->bankWithBranch();
            $component->set('bankId', $bank->id)->set('bankBranchId', $branch->id)->set('accountNo', '0012345678');
        } else {
            $component->set('isCash', true);
        }

        return $component->call('next');
    }

    public function test_new_person_is_hired_with_bank_and_enrolment(): void
    {
        $c = $this->company(['employee_no_prefix' => 'ECW', 'next_employee_seq' => 412]);
        $a = $this->asg($c);
        [$bank, $branch] = $this->bankWithBranch();
        $this->loginAs('hr-manager', $c);

        Livewire::test(HireWizard::class)
            ->set('companyId', $c->id)->set('nic', '853400937V')->call('next')
            ->assertSet('step', 2)->assertSet('dateOfBirth', '1985-12-05')->assertSet('gender', 'MALE')
            ->set('firstName', 'Nimal')->set('lastName', 'Perera')->set('nameWithInitials', 'N. Perera')->set('mobile', '0771234567')
            ->set('addressLine1', '12 Main St')->set('city', 'Nittambuwa')->call('next')
            ->set('type', 'PERMANENT')->set('hireDate', '2026-12-01')->set('epfNo', '900')
            ->set('orgUnitId', $a['org_unit_id'])->set('jobId', $a['job_id'])->set('gradeId', $a['grade_id'])->set('locationId', $a['location_id'])
            ->set('shiftId', $this->shift()->id)->call('next')
            ->set('bankId', $bank->id)->set('bankBranchId', $branch->id)->set('accountNo', '0012345678')->set('deviceGroup', 'NIT')->set('deviceUserId', 116)->call('next')
            ->assertSet('step', 5)->assertSee('The employee number will be issued when you save.')->call('hire')
            ->assertHasNoErrors()->assertRedirect();

        $e = Employment::sole();
        $this->assertSame('ECW412', $e->employee_no);
        $this->assertSame('198534000937', $e->person->nic);
        $this->assertSame('N. Perera', app(BankAccountService::class)->on($e, '2026-12-01')->account_name);
        $this->assertSame($e->id, app(EnrolmentService::class)->resolve('NIT', 116, '2026-12-01 08:00:00'));
        $this->assertSame('0771234567', $e->person->contacts()->where('type', 'MOBILE')->sole()->value);
        $this->assertSame('Nittambuwa', $e->person->addresses()->where('type', 'PERMANENT')->sole()->city);
    }

    public function test_hire_redirects_to_the_profile_with_a_flash_message(): void
    {
        $c = $this->company(['employee_no_prefix' => 'ECW', 'next_employee_seq' => 412]);
        $this->loginAs('hr-manager', $c);

        $this->walkToReview((string) $c->id)->call('hire');

        $this->assertSame('ECW412', Employment::sole()->employee_no);
        $this->assertSame('Hired as ECW412.', session('success'));
    }

    public function test_person_already_employed_is_stopped_at_step_one(): void
    {
        $e = $this->hired();
        $this->loginAs('hr-manager', $e->company);

        Livewire::test(HireWizard::class)->set('companyId', $e->company_id)->set('nic', $e->person->nic)->call('next')
            ->assertSet('step', 1)->assertSee('already employed')->assertSee($e->employee_no);
    }

    public function test_person_with_only_exited_employment_in_the_company_becomes_a_rejoin(): void
    {
        $c = $this->company();
        $p = $this->person();
        $old = app(EmploymentService::class)->exit($this->hired('2024-01-01', $c, p: $p), '2025-06-30', ExitReason::RESIGNATION);
        $this->loginAs('hr-manager', $c);

        Livewire::test(HireWizard::class)->set('companyId', $c->id)->set('nic', $p->nic)->call('next')
            ->assertSet('step', 2)->assertSet('mode', 'REJOIN')->assertSet('firstName', $p->first_name)->assertSee($old->employee_no);
    }

    public function test_rejoin_creates_a_new_employment_for_the_existing_person(): void
    {
        $c = $this->company(['employee_no_prefix' => 'ECW', 'next_employee_seq' => 50]);
        $p = $this->person();
        app(EmploymentService::class)->exit($this->hired('2024-01-01', $c, p: $p), '2025-06-30', ExitReason::RESIGNATION);
        $a = $this->asg($c);
        $this->loginAs('hr-manager', $c);

        Livewire::test(HireWizard::class)->set('companyId', $c->id)->set('nic', $p->nic)->call('next')
            ->set('mobile', '0771234567')->set('addressLine1', '1 Lane')->set('city', 'Gampaha')->call('next')
            ->set('type', 'PERMANENT')->set('hireDate', '2026-11-20')->set('epfNo', '4321')
            ->set('orgUnitId', $a['org_unit_id'])->set('jobId', $a['job_id'])->set('gradeId', $a['grade_id'])->set('locationId', $a['location_id'])
            ->set('shiftId', $this->shift()->id)->call('next')
            ->set('isCash', true)->call('next')
            ->call('hire')->assertHasNoErrors()->assertRedirect();

        $this->assertSame(1, Person::query()->where('nic', $p->nic)->count());
        $new = Employment::query()->where('person_id', $p->id)->where('status', 'ACTIVE')->sole();
        $this->assertNotNull($new->previous_employment_id);
        $this->assertSame(2, Employment::query()->where('person_id', $p->id)->count());
    }

    public function test_person_exited_from_another_company_is_hired_as_a_new_hire_of_the_same_person(): void
    {
        $a = $this->company();
        $b = $this->company();
        $p = $this->person();
        app(EmploymentService::class)->exit($this->hired('2024-01-01', $a, p: $p), '2025-06-30', ExitReason::RESIGNATION);
        $this->loginAs('hr-manager', $a, $b);

        Livewire::test(HireWizard::class)->set('companyId', $b->id)->set('nic', $p->nic)->call('next')
            ->assertSet('step', 2)->assertSet('mode', 'HIRE')->assertSet('firstName', $p->first_name);
    }

    public function test_back_keeps_what_was_entered(): void
    {
        $c = $this->company();
        $this->loginAs('hr-manager', $c);

        Livewire::test(HireWizard::class)
            ->set('companyId', $c->id)->set('nic', '853400937V')->call('next')
            ->set('firstName', 'Nimal')->call('back')
            ->assertSet('step', 1)->assertSet('nic', '853400937V')
            ->call('next')->assertSet('step', 2)->assertSet('firstName', 'Nimal');
    }

    public function test_intern_hides_epf_and_is_saved_non_liable(): void
    {
        $c = $this->company();
        $this->loginAs('hr-manager', $c);

        $component = $this->walkToReview((string) $c->id, ['type' => 'INTERN', 'epfNo' => ''], false);
        $component->assertSet('epfLiable', false);
        $component->call('hire')->assertHasNoErrors()->assertRedirect();

        $this->assertFalse(Employment::sole()->epf_liable);
    }

    public function test_choosing_intern_clears_the_epf_liability(): void
    {
        $c = $this->company();
        $this->loginAs('hr-manager', $c);

        Livewire::test(HireWizard::class)
            ->assertSet('epfLiable', true)
            ->set('type', 'INTERN')->assertSet('epfLiable', false)
            ->set('type', 'PERMANENT')->assertSet('epfLiable', true);
    }

    public function test_probation_defaults_the_confirmation_due_date_to_six_months(): void
    {
        $c = $this->company();
        $this->loginAs('hr-manager', $c);

        Livewire::test(HireWizard::class)
            ->set('hireDate', '2026-12-01')->set('type', 'PROBATION')
            ->assertSet('confirmationDueDate', '2027-06-01');
    }

    public function test_missing_required_fields_block_the_step(): void
    {
        $c = $this->company();
        $this->loginAs('hr-manager', $c);

        Livewire::test(HireWizard::class)
            ->set('companyId', $c->id)->set('nic', '853400937V')->call('next')
            ->assertSet('step', 2)
            ->call('next')
            ->assertSet('step', 2)
            ->assertHasErrors(['firstName', 'lastName', 'nameWithInitials', 'mobile']);
    }

    public function test_domain_error_on_save_shows_a_message_and_saves_nothing(): void
    {
        $c = $this->company();
        $this->hired('2026-01-01', $c, ['epf_no' => '900']);
        $this->loginAs('hr-manager', $c);
        $people = Person::count();
        $employments = Employment::count();

        $this->walkToReview((string) $c->id)
            ->call('hire')
            ->assertSee('EPF number is already in use')
            ->assertNoRedirect();

        $this->assertSame($people, Person::count());
        $this->assertSame($employments, Employment::count());
    }

    public function test_wizard_needs_employee_create(): void
    {
        $c = $this->company();
        $this->loginAs('hr-officer', $c);
        $this->get(route('employees.create'))->assertOk();

        $this->loginAs('auditor', $c);
        $this->get(route('employees.create'))->assertForbidden();
    }

    public function test_company_picker_only_lists_permitted_companies(): void
    {
        $mine = $this->company(['name' => 'Alpha Garments']);
        $theirs = $this->company(['name' => 'Omega Textiles']);
        $this->loginAs('hr-manager', $mine);

        Livewire::test(HireWizard::class)
            ->assertSee('Alpha Garments')
            ->assertDontSee('Omega Textiles');

        $this->assertNotNull($theirs);
    }

    public function test_company_outside_the_permitted_list_is_refused(): void
    {
        $mine = $this->company();
        $theirs = $this->company();
        $this->loginAs('hr-manager', $mine);

        Livewire::test(HireWizard::class)
            ->set('companyId', $theirs->id)->set('nic', '853400937V')->call('next')
            ->assertSet('step', 1)
            ->assertHasErrors(['companyId']);
    }

    public function test_invalid_nic_stays_on_step_one(): void
    {
        $c = $this->company();
        $this->loginAs('hr-manager', $c);

        Livewire::test(HireWizard::class)
            ->set('companyId', $c->id)->set('nic', '12345')->call('next')
            ->assertSet('step', 1)
            ->assertHasErrors(['nic']);
    }

    public function test_user_without_create_permission_cannot_hire(): void
    {
        $c = $this->company();
        $user = User::factory()->forCompanies([$c])->withPermissions('employee.view')->create(['must_change_password' => false]);
        $this->actingAs($user);

        Livewire::test(HireWizard::class)->call('hire')->assertForbidden();
    }
}
