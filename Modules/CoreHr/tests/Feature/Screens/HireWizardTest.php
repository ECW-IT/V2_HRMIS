<?php

namespace Modules\CoreHr\Tests\Feature\Screens;

use App\Models\User;
use App\Support\Company\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Modules\Attendance\Services\EnrolmentService;
use Modules\Attendance\Services\TimeProfileService;
use Modules\CoreHr\Database\Seeders\RegionSeeder;
use Modules\CoreHr\Database\Seeders\RelationshipSeeder;
use Modules\CoreHr\Enums\ArriveWorkFrom;
use Modules\CoreHr\Enums\ExitReason;
use Modules\CoreHr\Enums\MaritalStatus;
use Modules\CoreHr\Livewire\HireWizard;
use Modules\CoreHr\Models\District;
use Modules\CoreHr\Models\Employment;
use Modules\CoreHr\Models\GnDivision;
use Modules\CoreHr\Models\Person;
use Modules\CoreHr\Services\BankAccountService;
use Modules\CoreHr\Services\EmploymentService;
use Modules\Organisation\Models\Company;
use Modules\Organisation\Models\EmployeeCategory;
use Modules\Payroll\Enums\OtRateMode;
use Modules\Payroll\Services\CompensationService;
use Tests\Support\HrFixtures;
use Tests\TestCase;

class HireWizardTest extends TestCase
{
    use HrFixtures;
    use RefreshDatabase;

    private function seedHireLookups(): void
    {
        $this->seed(RegionSeeder::class);
        $this->seed(RelationshipSeeder::class);
    }

    /** @return array<string, mixed> */
    private function requiredPersonalFields(array $overrides = []): array
    {
        return array_merge([
            'fullName' => 'Nimal Perera',
            'callingName' => 'Nimal',
            'maritalStatus' => MaritalStatus::MARRIED->value,
            'nationality' => 'Sri Lankan',
            'region' => 'Buddhism',
            'arriveWorkFrom' => ArriveWorkFrom::HOME->value,
            'province' => 'Western',
            'electorateDistrict' => 'Gampaha',
            'gnDivision' => 'Nittambuwa North',
            'mobile' => '0771234567',
            'addressLine1' => '12 Main St',
            'city' => 'Nittambuwa',
            'emergencyContacts' => [
                ['name' => 'Kamala Perera', 'relationship' => 'Wife', 'phone' => '0771111111'],
            ],
        ], $overrides);
    }

    private function employeeCategoryId(): int
    {
        return EmployeeCategory::factory()->create()->id;
    }

    /** Walks the wizard from step 1 to step 5 for a new person. */
    private function walkToReview(string $companyId, array $employment = [], bool $withBank = true): Testable
    {
        $this->seedHireLookups();
        $this->travelTo($employment['hireDate'] ?? '2026-12-01');
        $a = $this->asg(Company::query()->findOrFail($companyId));
        $categoryId = $this->employeeCategoryId();

        $component = Livewire::test(HireWizard::class)
            ->set('companyId', $companyId)->set('nic', '853400937V')->call('next');

        foreach ($this->requiredPersonalFields() as $property => $value) {
            $component->set($property, $value);
        }
        $component->call('next');

        $shift = $this->shift();

        foreach (array_merge([
            'type' => 'PERMANENT',
            'hireDate' => '2026-12-01',
            'epfNo' => '900',
            'employeeCategoryId' => $categoryId,
            'orgUnitId' => $a['org_unit_id'],
            'jobId' => $a['job_id'],
            'shiftId' => $shift->id,
        ], $employment) as $property => $value) {
            $component->set($property, $value);
        }

        $component->call('next');

        $salary = ($employment['type'] ?? 'PERMANENT') === 'INTERN' ? '0.00' : '55000.00';
        $component->set('basicSalary', $employment['basicSalary'] ?? $salary)
            ->set('otRateMode', $employment['otRateMode'] ?? OtRateMode::CALCULATED->value);

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
        $this->seedHireLookups();
        $this->travelTo('2026-12-01');
        $c = $this->company(['employee_no_prefix' => 'ECW', 'next_employee_seq' => 412]);
        $a = $this->asg($c);
        [$bank, $branch] = $this->bankWithBranch();
        $district = District::factory()->create(['code' => 'GAM', 'name' => 'Gampaha']);
        GnDivision::factory()->create(['district_id' => $district->id, 'code' => 'GN-NIT', 'name' => 'Nittambuwa North']);
        $categoryId = $this->employeeCategoryId();
        $this->loginAs('hr-manager', $c);

        $component = Livewire::test(HireWizard::class)
            ->set('companyId', $c->id)->set('nic', '853400937V')->call('next')
            ->assertSet('step', 2)->assertSet('dateOfBirth', '1985-12-05')->assertSet('gender', 'MALE')
            ->assertSee('2045-12-05');

        foreach ($this->requiredPersonalFields([
            'fullName' => 'Nimal S Perera',
            'nationality' => 'Nepalese',
            'distanceKm' => '12.5',
        ]) as $property => $value) {
            $component->set($property, $value);
        }

        $component->assertSet('nameWithInitials', 'N. S. Perera')
            ->assertSee('Nittambuwa North')
            ->assertSeeHtml('gnDivisionPicker')
            ->assertSeeHtml('Search GN division')
            ->call('next')
            ->assertSee('ECW412')
            ->assertDontSee('Address line 2')
            ->assertDontSee('Postal code')
            ->set('type', 'PERMANENT')->set('hireDate', '2026-12-01')->set('epfNo', '900')
            ->set('employeeCategoryId', $categoryId)
            ->set('orgUnitId', $a['org_unit_id'])->set('jobId', $a['job_id'])
            ->set('shiftId', $this->shift()->id)->call('next')
            ->set('basicSalary', '55000.00')->set('otRateMode', OtRateMode::CALCULATED->value)
            ->set('bankId', $bank->id)->set('bankBranchId', $branch->id)->set('accountNo', '0012345678')->set('deviceGroup', 'NIT')->set('deviceUserId', 116)->call('next')
            ->assertSet('step', 5)->assertSee('The employee number will be issued when you save.')->assertSee('Western')->assertSee('Nimal S Perera')->call('hire')
            ->assertHasNoErrors()->assertRedirect();

        $e = Employment::sole();
        $this->assertSame('ECW412', $e->employee_no);
        $this->assertSame('198534000937', $e->person->nic);
        $this->assertSame('Nimal S Perera', $e->person->full_name);
        $this->assertSame('Nimal', $e->person->first_name);
        $this->assertSame('S Perera', $e->person->last_name);
        $this->assertSame('N. S. Perera', $e->person->name_with_initials);
        $this->assertSame('Nepalese', $e->person->nationality);
        $this->assertSame('N. S. Perera', app(BankAccountService::class)->on($e, '2026-12-01')->account_name);
        $comp = app(CompensationService::class)->on($e, '2026-12-01');
        $this->assertNotNull($comp);
        $this->assertSame('55000.00', $comp->basic_salary);
        $this->assertSame(OtRateMode::CALCULATED, $comp->ot_rate_mode);
        $this->assertSame($e->id, app(EnrolmentService::class)->resolve('NIT', 116, '2026-12-01 08:00:00'));
        $this->assertSame('0771234567', $e->person->contacts()->where('type', 'MOBILE')->sole()->value);
        $address = $e->person->addresses()->where('type', 'PERMANENT')->sole();
        $this->assertSame('Nittambuwa', $address->city);
        $this->assertNull($address->district);
        $this->assertNull($address->line2);
        $this->assertNull($address->postal_code);
        $this->assertSame('Western', $e->person->province);
        $this->assertSame('Gampaha', $e->person->electorate_district);
        $this->assertSame('Nittambuwa North', $e->person->gn_division);
        $this->assertSame('12.5', (string) $e->person->distance_km);
        $this->assertSame('PUNCH', app(TimeProfileService::class)->on($e, '2026-12-01')->attendance_mode->value);
    }

    public function test_hire_redirects_to_the_profile_with_a_flash_message(): void
    {
        $c = $this->company(['employee_no_prefix' => 'ECW', 'next_employee_seq' => 412]);
        $this->loginAs('hr-manager', $c);

        $this->walkToReview((string) $c->id)->call('hire');

        $this->assertSame('ECW412', Employment::sole()->employee_no);
        $this->assertSame('Hired as ECW412.', session('success'));
    }

    public function test_hire_saves_multiple_emergency_contacts(): void
    {
        $this->seedHireLookups();
        $c = $this->company(['employee_no_prefix' => 'ECW', 'next_employee_seq' => 500]);
        $a = $this->asg($c);
        $categoryId = $this->employeeCategoryId();
        $this->loginAs('hr-manager', $c);
        $this->travelTo('2026-12-01');

        $component = Livewire::test(HireWizard::class)
            ->set('companyId', $c->id)->set('nic', '853400937V')->call('next');

        foreach ($this->requiredPersonalFields([
            'emergencyContacts' => [
                ['name' => 'Kamala Perera', 'relationship' => 'Wife', 'phone' => '0771111111'],
                ['name' => 'Sunil Perera', 'relationship' => 'Brother', 'phone' => '0772222222'],
            ],
        ]) as $property => $value) {
            $component->set($property, $value);
        }

        $component->call('next')
            ->set('type', 'PERMANENT')->set('hireDate', '2026-12-01')->set('epfNo', '900')
            ->set('employeeCategoryId', $categoryId)
            ->set('orgUnitId', $a['org_unit_id'])->set('jobId', $a['job_id'])
            ->set('shiftId', $this->shift()->id)->call('next')
            ->set('basicSalary', '55000.00')->set('otRateMode', OtRateMode::CALCULATED->value)
            ->set('isCash', true)->call('next')
            ->assertSee('Kamala Perera')->assertSee('Sunil Perera')
            ->call('hire')
            ->assertHasNoErrors()->assertRedirect();

        $person = Employment::sole()->person;
        $contacts = $person->emergencyContacts()->orderBy('id')->get();
        $this->assertCount(2, $contacts);
        $this->assertSame('Kamala Perera', $contacts[0]->name);
        $this->assertSame('Wife', $contacts[0]->relationship);
        $this->assertSame('0771111111', $contacts[0]->phone);
        $this->assertSame('Sunil Perera', $contacts[1]->name);
        $this->assertSame('Brother', $contacts[1]->relationship);
        $this->assertSame('0772222222', $contacts[1]->phone);
    }

    public function test_hire_saves_licence_phones_region_and_arrive_work_from(): void
    {
        $this->seedHireLookups();
        $c = $this->company(['employee_no_prefix' => 'ECW', 'next_employee_seq' => 501]);
        $a = $this->asg($c);
        $categoryId = $this->employeeCategoryId();
        $this->loginAs('hr-manager', $c);
        $this->travelTo('2026-12-01');

        $component = Livewire::test(HireWizard::class)
            ->set('companyId', $c->id)->set('nic', '853400937V')->call('next');

        foreach ($this->requiredPersonalFields([
            'drivingLicenceNo' => 'B1234567',
            'landlineNo' => '0112345678',
            'officeNo' => '0118765432',
            'arriveWorkFrom' => ArriveWorkFrom::BOARDING->value,
        ]) as $property => $value) {
            $component->set($property, $value);
        }

        $component->call('next')
            ->set('type', 'PERMANENT')->set('hireDate', '2026-12-01')->set('epfNo', '900')
            ->set('employeeCategoryId', $categoryId)
            ->set('orgUnitId', $a['org_unit_id'])->set('jobId', $a['job_id'])
            ->set('shiftId', $this->shift()->id)->call('next')
            ->set('basicSalary', '55000.00')->set('otRateMode', OtRateMode::CALCULATED->value)
            ->set('isCash', true)->call('next')
            ->assertSee('B1234567')->assertSee('Buddhism')->assertSee('Boarding')
            ->call('hire')
            ->assertHasNoErrors()->assertRedirect();

        $person = Employment::sole()->person;
        $this->assertSame('B1234567', $person->driving_licence_no);
        $this->assertSame('0112345678', $person->landline_no);
        $this->assertSame('0118765432', $person->office_no);
        $this->assertSame('Buddhism', $person->region);
        $this->assertSame(ArriveWorkFrom::BOARDING, $person->arrive_work_from);
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
            ->assertSet('step', 2)->assertSet('mode', 'REJOIN')
            ->assertSet('fullName', trim(implode(' ', array_filter([$p->first_name, $p->middle_name, $p->last_name]))))
            ->assertSee($old->employee_no);
    }

    public function test_rejoin_creates_a_new_employment_for_the_existing_person(): void
    {
        $this->seedHireLookups();
        $c = $this->company(['employee_no_prefix' => 'ECW', 'next_employee_seq' => 50]);
        $p = $this->person();
        app(EmploymentService::class)->exit($this->hired('2024-01-01', $c, p: $p), '2025-06-30', ExitReason::RESIGNATION);
        $a = $this->asg($c);
        $categoryId = $this->employeeCategoryId();
        $this->loginAs('hr-manager', $c);

        $component = Livewire::test(HireWizard::class)->set('companyId', $c->id)->set('nic', $p->nic)->call('next');
        foreach ($this->requiredPersonalFields([
            'fullName' => trim(implode(' ', array_filter([$p->first_name, $p->middle_name, $p->last_name]))),
            'addressLine1' => '1 Lane',
            'city' => 'Gampaha',
        ]) as $property => $value) {
            $component->set($property, $value);
        }

        $component->call('next')
            ->set('type', 'PERMANENT')->set('hireDate', '2026-11-20')->set('epfNo', '4321')
            ->set('employeeCategoryId', $categoryId)
            ->set('orgUnitId', $a['org_unit_id'])->set('jobId', $a['job_id'])
            ->set('shiftId', $this->shift()->id)->call('next')
            ->set('basicSalary', '45000.00')->set('isCash', true)->call('next')
            ->call('hire')->assertHasNoErrors()->assertRedirect();

        $this->assertSame(1, Person::query()->where('nic', $p->nic)->count());
        $new = Employment::query()->where('person_id', $p->id)->where('status', 'ACTIVE')->sole();
        $this->assertNotNull($new->previous_employment_id);
        $this->assertSame(2, Employment::query()->where('person_id', $p->id)->count());
        $this->assertSame('45000.00', app(CompensationService::class)->on($new, $new->hire_date)->basic_salary);
    }

    public function test_person_exited_from_another_company_is_hired_as_a_new_hire_of_the_same_person(): void
    {
        $a = $this->company();
        $b = $this->company();
        $p = $this->person();
        app(EmploymentService::class)->exit($this->hired('2024-01-01', $a, p: $p), '2025-06-30', ExitReason::RESIGNATION);
        $this->loginAs('hr-manager', $a, $b);
        CompanyContext::set($b->id);

        Livewire::test(HireWizard::class)->set('nic', $p->nic)->call('next')
            ->assertSet('step', 2)->assertSet('mode', 'HIRE')
            ->assertSet('fullName', trim(implode(' ', array_filter([$p->first_name, $p->middle_name, $p->last_name]))));
    }

    public function test_back_keeps_what_was_entered(): void
    {
        $c = $this->company();
        $this->loginAs('hr-manager', $c);

        Livewire::test(HireWizard::class)
            ->set('companyId', $c->id)->set('nic', '853400937V')->call('next')
            ->set('fullName', 'Nimal Perera')->call('back')
            ->assertSet('step', 1)->assertSet('nic', '853400937V')
            ->call('next')->assertSet('step', 2)->assertSet('fullName', 'Nimal Perera');
    }

    public function test_intern_hides_epf_and_is_saved_non_liable(): void
    {
        $c = $this->company();
        $this->loginAs('hr-manager', $c);

        $component = $this->walkToReview((string) $c->id, ['type' => 'INTERN', 'epfNo' => ''], false);
        $component->assertSet('epfLiable', false)->assertSet('basicSalary', '0.00');
        $component->call('hire')->assertHasNoErrors()->assertRedirect();

        $e = Employment::sole();
        $this->assertFalse($e->epf_liable);
        $this->assertSame('0.00', app(CompensationService::class)->on($e, $e->hire_date)->basic_salary);
    }

    public function test_choosing_intern_clears_the_epf_liability(): void
    {
        $c = $this->company();
        $this->loginAs('hr-manager', $c);

        Livewire::test(HireWizard::class)
            ->assertSet('epfLiable', true)
            ->set('type', 'INTERN')->assertSet('epfLiable', false)->assertSet('basicSalary', '0.00')
            ->set('type', 'PERMANENT')->assertSet('epfLiable', true)->assertSet('basicSalary', '');
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
        $this->seedHireLookups();
        $c = $this->company();
        $this->loginAs('hr-manager', $c);

        Livewire::test(HireWizard::class)
            ->set('companyId', $c->id)->set('nic', '853400937V')->call('next')
            ->assertSet('step', 2)
            ->call('next')
            ->assertSet('step', 2)
            ->assertHasErrors([
                'fullName',
                'nameWithInitials',
                'callingName',
                'maritalStatus',
                'region',
                'arriveWorkFrom',
                'province',
                'electorateDistrict',
                'gnDivision',
                'mobile',
                'emergencyContacts.0.name',
            ]);
    }

    public function test_missing_employee_category_blocks_step_three(): void
    {
        $this->seedHireLookups();
        $c = $this->company();
        $a = $this->asg($c);
        $this->loginAs('hr-manager', $c);
        $this->travelTo('2026-12-01');

        $component = Livewire::test(HireWizard::class)
            ->set('companyId', $c->id)->set('nic', '853400937V')->call('next');

        foreach ($this->requiredPersonalFields() as $property => $value) {
            $component->set($property, $value);
        }

        $component->call('next')
            ->set('type', 'PERMANENT')->set('hireDate', '2026-12-01')->set('epfNo', '900')
            ->set('orgUnitId', $a['org_unit_id'])->set('jobId', $a['job_id'])
            ->set('shiftId', $this->shift()->id)
            ->call('next')
            ->assertSet('step', 3)
            ->assertHasErrors(['employeeCategoryId']);
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

    public function test_step_one_uses_session_company_and_hides_company_picker(): void
    {
        $mine = $this->company(['name' => 'Alpha Garments']);
        $theirs = $this->company(['name' => 'Omega Textiles']);
        $this->loginAs('hr-manager', $mine);
        CompanyContext::set($mine->id);

        Livewire::test(HireWizard::class)
            ->assertSet('companyId', $mine->id)
            ->assertDontSeeHtml('wire:model="companyId"')
            ->assertDontSee('Omega Textiles');

        $this->assertNotNull($theirs);
    }

    public function test_tampered_company_id_is_replaced_by_session_company(): void
    {
        $mine = $this->company();
        $theirs = $this->company();
        $this->loginAs('hr-manager', $mine);
        CompanyContext::set($mine->id);

        Livewire::test(HireWizard::class)
            ->set('companyId', $theirs->id)->set('nic', '853400937V')->call('next')
            ->assertHasNoErrors(['companyId'])
            ->assertSet('companyId', $mine->id)
            ->assertSet('step', 2);
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
