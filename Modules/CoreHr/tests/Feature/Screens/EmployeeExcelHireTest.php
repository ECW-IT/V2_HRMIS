<?php

namespace Modules\CoreHr\Tests\Feature\Screens;

use App\Models\User;
use App\Support\Company\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Attendance\Models\BiometricEnrolment;
use Modules\Attendance\Models\DeviceGroup;
use Modules\CoreHr\Enums\ArriveWorkFrom;
use Modules\CoreHr\Exports\EmployeeHireTemplateExport;
use Modules\CoreHr\Livewire\EmployeesIndex;
use Modules\CoreHr\Models\Employment;
use Modules\CoreHr\Models\Region;
use Modules\Organisation\Models\Designation;
use Modules\Organisation\Models\Location;
use Modules\Organisation\Models\OrgUnit;
use Modules\Payroll\Enums\CalcMethod;
use Modules\Payroll\Enums\ComponentKind;
use Modules\Payroll\Enums\OtRateMode;
use Modules\Payroll\Models\EmployeeComponent;
use Modules\Payroll\Models\PayComponent;
use Tests\Support\HrFixtures;
use Tests\TestCase;

class EmployeeExcelHireTest extends TestCase
{
    use HrFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_download_hire_template_requires_create_permission(): void
    {
        $a = $this->company();
        $user = User::factory()->forCompanies([$a])->withPermissions('employee.view')->create([
            'must_change_password' => false,
        ]);
        $this->actingAs($user);
        CompanyContext::set($a->id);

        Livewire::test(EmployeesIndex::class)
            ->call('downloadHireTemplate')
            ->assertForbidden();
    }

    public function test_download_hire_template_downloads_for_hr_officer(): void
    {
        $a = $this->company();
        $this->loginAs('hr-officer', $a);
        CompanyContext::set($a->id);

        Excel::fake();

        Livewire::test(EmployeesIndex::class)
            ->call('downloadHireTemplate');

        Excel::assertDownloaded('employee_hire_template.xlsx', function (EmployeeHireTemplateExport $export) {
            $sheets = $export->sheets();
            $headings = $sheets[0]->headings();

            return count($sheets) === 2
                && $sheets[0]->title() === 'Hires'
                && $sheets[1]->title() === 'Lists'
                && in_array('nic', $headings, true)
                && in_array('org_unit', $headings, true)
                && in_array('full_name', $headings, true)
                && in_array('region', $headings, true)
                && in_array('device_group', $headings, true)
                && in_array('fingerprint_id', $headings, true)
                && in_array('earning_type', $headings, true)
                && in_array('hostel_available', $headings, true);
        });
    }

    public function test_upload_hires_creates_employment_from_valid_row(): void
    {
        $a = $this->company();
        $this->loginAs('hr-officer', $a);
        CompanyContext::set($a->id);

        $org = OrgUnit::factory()->create(['company_id' => $a->id, 'name' => 'Assembly', 'is_active' => true]);
        $designation = Designation::factory()->create(['name' => 'Welder', 'is_active' => true]);
        $location = Location::factory()->create(['name' => 'Plant A', 'is_active' => true]);
        $shift = $this->shift(['name' => 'Day Shift', 'code' => 'DAY01']);

        $upload = $this->makeHireUpload([$this->baseHireRow([
            'passport_no' => 'PP-EXCEL-001',
            'first_name' => 'Nimal',
            'last_name' => 'Perera',
            'name_with_initials' => 'N. Perera',
            'calling_name' => 'Nimal',
            'date_of_birth' => '1990-01-15',
            'mobile' => '0771234567',
            'address_line_1' => '12 Main Street',
            'city' => 'Colombo',
            'hire_date' => '2026-03-01',
            'org_unit' => $org->name,
            'designation' => $designation->name,
            'location' => $location->name,
            'shift' => $shift->name,
            'basic_salary' => '55000.00',
            'emergency_contact_name' => 'Kamala Perera',
            'emergency_contact_relationship' => 'Spouse',
            'emergency_contact_phone' => '0777654321',
        ])]);

        Livewire::test(EmployeesIndex::class)
            ->call('openHireUpload')
            ->set('hireExcelFile', $upload)
            ->call('uploadHires')
            ->assertHasNoErrors()
            ->assertSet('hireImportErrors', [])
            ->assertSee('Success: 1');

        $employment = Employment::query()->where('company_id', $a->id)->latest('id')->first();
        $this->assertNotNull($employment);
        $this->assertSame('PERMANENT', $employment->type->value);
        $this->assertSame('2026-03-01', $employment->hire_date->toDateString());
        $this->assertSame('N. Perera', $employment->person->name_with_initials);
        $this->assertSame('PP-EXCEL-001', $employment->person->passport_no);
    }

    public function test_upload_softens_nic_fingerprint_and_zero_salary(): void
    {
        $a = $this->company();
        $this->loginAs('hr-officer', $a);
        CompanyContext::set($a->id);

        $org = OrgUnit::factory()->create(['company_id' => $a->id, 'name' => 'Soft Shop', 'is_active' => true]);
        $designation = Designation::factory()->create(['name' => 'Soft Worker', 'is_active' => true]);
        $location = Location::factory()->create(['name' => 'Soft Site', 'is_active' => true]);
        $shift = $this->shift(['name' => 'Soft Day', 'code' => 'SOFT01']);
        DeviceGroup::factory()->create(['code' => 'SOFTG', 'name' => 'Soft Group', 'is_active' => true]);

        $upload = $this->makeHireUpload([
            $this->baseHireRow([
                'nic' => '198810099998',
                'passport_no' => '',
                'first_name' => 'Nic',
                'last_name' => 'Match',
                'name_with_initials' => 'N. Match',
                'calling_name' => 'Nic',
                'date_of_birth' => '2000-01-01',
                'gender' => 'FEMALE',
                'mobile' => '0775000001',
                'address_line_1' => '6 Road',
                'city' => 'Colombo',
                'hire_date' => '2026-11-15',
                'org_unit' => $org->name,
                'designation' => $designation->name,
                'location' => $location->name,
                'shift' => $shift->name,
                'basic_salary' => '0',
                'device_group' => 'SOFTG',
                'fingerprint_id' => '',
                'emergency_contact_name' => 'Contact',
                'emergency_contact_relationship' => 'Friend',
                'emergency_contact_phone' => '0775000002',
            ]),
            $this->baseHireRow([
                'nic' => 'NOTANIC',
                'passport_no' => 'PP-INVALID-NIC',
                'first_name' => 'Bad',
                'last_name' => 'Nic',
                'name_with_initials' => 'B. Nic',
                'calling_name' => 'Bad',
                'date_of_birth' => '1995-05-05',
                'gender' => 'MALE',
                'mobile' => '0775000003',
                'address_line_1' => '7 Road',
                'city' => 'Colombo',
                'hire_date' => '2026-11-15',
                'org_unit' => $org->name,
                'designation' => $designation->name,
                'location' => $location->name,
                'shift' => $shift->name,
                'basic_salary' => '25000',
                'emergency_contact_name' => 'Contact',
                'emergency_contact_relationship' => 'Friend',
                'emergency_contact_phone' => '0775000004',
            ]),
        ]);

        Livewire::test(EmployeesIndex::class)
            ->call('openHireUpload')
            ->set('hireExcelFile', $upload)
            ->call('uploadHires')
            ->assertHasNoErrors()
            ->assertSet('hireImportErrors', [])
            ->assertSee('Success: 2');

        $matched = Employment::query()->whereHas('person', fn ($q) => $q->where('nic', '198810099998'))->first();
        $this->assertNotNull($matched);
        $this->assertSame('1988-04-09', $matched->person->date_of_birth->toDateString());
        $this->assertSame('MALE', $matched->person->gender->value);
        $this->assertSame(0, BiometricEnrolment::query()->where('employment_id', $matched->id)->count());

        $invalidNic = Employment::query()->whereHas('person', fn ($q) => $q->where('passport_no', 'PP-INVALID-NIC'))->first();
        $this->assertNotNull($invalidNic);
        $this->assertNull($invalidNic->person->nic);
    }

    public function test_upload_accepts_unmarried_and_soft_ot_attendance(): void
    {
        $a = $this->company();
        $this->loginAs('hr-officer', $a);
        CompanyContext::set($a->id);

        $org = OrgUnit::factory()->create(['company_id' => $a->id, 'name' => 'Marital Shop', 'is_active' => true]);
        $designation = Designation::factory()->create(['name' => 'Marital Worker', 'is_active' => true]);
        $location = Location::factory()->create(['name' => 'Marital Site', 'is_active' => true]);
        $shift = $this->shift(['name' => 'Marital Day', 'code' => 'MAR01']);

        $upload = $this->makeHireUpload([$this->baseHireRow([
            'passport_no' => 'PP-MAR-001',
            'first_name' => 'Single',
            'last_name' => 'Person',
            'name_with_initials' => 'S. Person',
            'calling_name' => 'Single',
            'date_of_birth' => '1993-04-04',
            'marital_status' => 'Unmarried',
            'mobile' => '0774000001',
            'address_line_1' => '5 Road',
            'city' => 'Colombo',
            'hire_date' => '2026-11-01',
            'org_unit' => $org->name,
            'designation' => $designation->name,
            'location' => $location->name,
            'shift' => $shift->name,
            'basic_salary' => '33000.00',
            'ot_rate_mode' => 'FIXED',
            'ot_rate' => '0',
            'attendance_type' => 'type1',
            'emergency_contact_name' => 'Contact',
            'emergency_contact_relationship' => 'Friend',
            'emergency_contact_phone' => '0774000002',
        ])]);

        Livewire::test(EmployeesIndex::class)
            ->call('openHireUpload')
            ->set('hireExcelFile', $upload)
            ->call('uploadHires')
            ->assertHasNoErrors()
            ->assertSet('hireImportErrors', [])
            ->assertSee('Success: 1');

        $employment = Employment::query()->whereHas('person', fn ($q) => $q->where('passport_no', 'PP-MAR-001'))->first();
        $this->assertNotNull($employment);
        $this->assertSame('SINGLE', $employment->person->marital_status->value);
    }

    public function test_upload_treats_dash_placeholders_and_rejects_an_unknown_category(): void
    {
        $a = $this->company();
        $this->loginAs('hr-officer', $a);
        CompanyContext::set($a->id);

        $org = OrgUnit::factory()->create(['company_id' => $a->id, 'name' => 'Dash Shop', 'is_active' => true]);
        $fallbackDesignation = Designation::factory()->create(['name' => 'Helper Fallback', 'is_active' => true]);
        $location = Location::factory()->create(['name' => 'Dash Site', 'is_active' => true]);
        $shift = $this->shift(['name' => 'Dash Day', 'code' => 'DASH01']);

        $upload = $this->makeHireUpload([$this->baseHireRow([
            'passport_no' => 'PP-DASH-001',
            'first_name' => 'Dash',
            'last_name' => 'Person',
            'name_with_initials' => 'D. Person',
            'calling_name' => 'Dash',
            'date_of_birth' => '1992-02-02',
            'gender' => '-',
            'region' => '-',
            'arrive_work_from' => '-',
            'electorate_district' => '-',
            'mobile' => '0773000001',
            'address_line_1' => '4 Road',
            'city' => 'Colombo',
            'hire_date' => '2026-10-01',
            'org_unit' => $org->name,
            'designation' => 'Junior Auto Electrical Technician',
            'location' => $location->name,
            'shift' => $shift->name,
            'employee_category' => '',
            'basic_salary' => '32000.00',
            'emergency_contact_name' => 'Contact',
            'emergency_contact_relationship' => 'Friend',
            'emergency_contact_phone' => '0773000002',
        ])]);

        Livewire::test(EmployeesIndex::class)
            ->call('openHireUpload')
            ->set('hireExcelFile', $upload)
            ->call('uploadHires')
            ->assertHasNoErrors()
            ->assertSet('hireImportErrors', [])
            ->assertSee('Success: 1');

        $employment = Employment::query()->whereHas('person', fn ($q) => $q->where('passport_no', 'PP-DASH-001'))->first();
        $this->assertNotNull($employment);
        $this->assertSame('MALE', $employment->person->gender->value);
        $this->assertNull($employment->person->region);
        $this->assertNull($employment->person->arrive_work_from);
        $this->assertNull($employment->person->electorate_district);
        $this->assertNull($employment->employee_category_id);
        $assignment = $employment->assignmentOn('2026-10-01');
        $this->assertNotNull($assignment);
        $this->assertSame($fallbackDesignation->id, $assignment->designation_id);

        $unknown = $this->makeHireUpload([$this->baseHireRow([
            'passport_no' => 'PP-DASH-002',
            'first_name' => 'Dash',
            'last_name' => 'Two',
            'name_with_initials' => 'D. Two',
            'calling_name' => 'Dash',
            'date_of_birth' => '1992-02-02',
            'mobile' => '0773000003',
            'address_line_1' => '4 Road',
            'city' => 'Colombo',
            'hire_date' => '2026-10-01',
            'org_unit' => $org->name,
            'designation' => $fallbackDesignation->name,
            'location' => $location->name,
            'shift' => $shift->name,
            'employee_category' => 'Production Staff',
            'basic_salary' => '32000.00',
            'emergency_contact_name' => 'Contact',
            'emergency_contact_relationship' => 'Friend',
            'emergency_contact_phone' => '0773000002',
        ])]);

        Livewire::test(EmployeesIndex::class)
            ->call('openHireUpload')
            ->set('hireExcelFile', $unknown)
            ->call('uploadHires')
            ->assertSee("Category 'Production Staff' not found.");
    }

    public function test_upload_accepts_default_late_deduction(): void
    {
        $a = $this->company();
        $this->loginAs('hr-officer', $a);
        CompanyContext::set($a->id);

        $org = OrgUnit::factory()->create(['company_id' => $a->id, 'name' => 'Late Shop', 'is_active' => true]);
        $designation = Designation::factory()->create(['name' => 'Late Worker', 'is_active' => true]);
        $location = Location::factory()->create(['name' => 'Late Site', 'is_active' => true]);
        $shift = $this->shift(['name' => 'Late Day', 'code' => 'LATEDAY']);

        $upload = $this->makeHireUpload([$this->baseHireRow([
            'passport_no' => 'PP-LATE-001',
            'first_name' => 'Late',
            'last_name' => 'Worker',
            'name_with_initials' => 'L. Worker',
            'calling_name' => 'Late',
            'date_of_birth' => '1991-03-03',
            'mobile' => '0772000001',
            'address_line_1' => '3 Road',
            'city' => 'Colombo',
            'hire_date' => '2026-09-01',
            'org_unit' => $org->name,
            'designation' => $designation->name,
            'location' => $location->name,
            'shift' => $shift->name,
            'basic_salary' => '31000.00',
            'late_deduction' => 'Default',
            'emergency_contact_name' => 'Contact',
            'emergency_contact_relationship' => 'Friend',
            'emergency_contact_phone' => '0772000002',
        ])]);

        Livewire::test(EmployeesIndex::class)
            ->call('openHireUpload')
            ->set('hireExcelFile', $upload)
            ->call('uploadHires')
            ->assertHasNoErrors()
            ->assertSet('hireImportErrors', [])
            ->assertSee('Success: 1');

        $employment = Employment::query()->whereHas('person', fn ($q) => $q->where('passport_no', 'PP-LATE-001'))->first();
        $this->assertNotNull($employment);
        $this->assertFalse((bool) $employment->timeProfiles()->latest('id')->first()?->late_deduction);
    }

    public function test_upload_accepts_legacy_ot_mode_labels(): void
    {
        $a = $this->company();
        $this->loginAs('hr-officer', $a);
        CompanyContext::set($a->id);

        $org = OrgUnit::factory()->create(['company_id' => $a->id, 'name' => 'OT Shop', 'is_active' => true]);
        $designation = Designation::factory()->create(['name' => 'OT Worker', 'is_active' => true]);
        $location = Location::factory()->create(['name' => 'OT Site', 'is_active' => true]);
        $shift = $this->shift(['name' => 'OT Day', 'code' => 'OTDAY']);

        $upload = $this->makeHireUpload([
            $this->baseHireRow([
                'passport_no' => 'PP-OT-001',
                'first_name' => 'OtOne',
                'last_name' => 'Worker',
                'name_with_initials' => 'O. Worker',
                'calling_name' => 'OtOne',
                'date_of_birth' => '1990-01-01',
                'mobile' => '0771000001',
                'address_line_1' => '1 Road',
                'city' => 'Colombo',
                'hire_date' => '2026-08-01',
                'org_unit' => $org->name,
                'designation' => $designation->name,
                'location' => $location->name,
                'shift' => $shift->name,
                'basic_salary' => '30000.00',
                'ot_mode' => 'Default',
                'emergency_contact_name' => 'Contact',
                'emergency_contact_relationship' => 'Friend',
                'emergency_contact_phone' => '0771000002',
            ]),
            $this->baseHireRow([
                'passport_no' => 'PP-OT-002',
                'first_name' => 'OtTwo',
                'last_name' => 'Worker',
                'name_with_initials' => 'O. Worker',
                'calling_name' => 'OtTwo',
                'date_of_birth' => '1990-01-01',
                'mobile' => '0771000003',
                'address_line_1' => '2 Road',
                'city' => 'Colombo',
                'hire_date' => '2026-08-01',
                'org_unit' => $org->name,
                'designation' => $designation->name,
                'location' => $location->name,
                'shift' => $shift->name,
                'basic_salary' => '30000.00',
                'ot_mode' => 'Evening Only',
                'emergency_contact_name' => 'Contact',
                'emergency_contact_relationship' => 'Friend',
                'emergency_contact_phone' => '0771000004',
            ]),
        ]);

        Livewire::test(EmployeesIndex::class)
            ->call('openHireUpload')
            ->set('hireExcelFile', $upload)
            ->call('uploadHires')
            ->assertHasNoErrors()
            ->assertSet('hireImportErrors', [])
            ->assertSee('Success: 2');

        $first = Employment::query()->whereHas('person', fn ($q) => $q->where('passport_no', 'PP-OT-001'))->first();
        $second = Employment::query()->whereHas('person', fn ($q) => $q->where('passport_no', 'PP-OT-002'))->first();
        $this->assertNotNull($first);
        $this->assertNotNull($second);
        $this->assertSame('NONE', $first->timeProfiles()->latest('id')->first()?->ot_mode?->value);
        $this->assertSame('EVENING', $second->timeProfiles()->latest('id')->first()?->ot_mode?->value);
    }

    public function test_upload_resolves_shift_time_range_and_blank_grade(): void
    {
        $a = $this->company();
        $this->loginAs('hr-officer', $a);
        CompanyContext::set($a->id);

        $org = OrgUnit::factory()->create(['company_id' => $a->id, 'name' => 'Paint', 'is_active' => true]);
        $designation = Designation::factory()->create(['name' => 'Painter', 'is_active' => true]);
        $location = Location::factory()->create(['name' => 'Booth', 'is_active' => true]);
        $shift = $this->shift([
            'name' => 'Day 07:30–16:30',
            'code' => 'D0730',
            'start_time' => '07:30',
            'end_time' => '16:30',
        ]);

        $upload = $this->makeHireUpload([$this->baseHireRow([
            'passport_no' => 'PP-SHIFT-001',
            'first_name' => 'Sahan',
            'last_name' => 'Perera',
            'name_with_initials' => 'S. Perera',
            'calling_name' => 'Sahan',
            'date_of_birth' => '1994-01-01',
            'mobile' => '0776677889',
            'address_line_1' => '1 Main St',
            'city' => 'Colombo',
            'hire_date' => '2026-07-01',
            'org_unit' => $org->name,
            'designation' => $designation->name,
            'location' => $location->name,
            'shift' => '07.30-16.30',
            'basic_salary' => '42000.00',
            'emergency_contact_name' => 'Contact',
            'emergency_contact_relationship' => 'Mother',
            'emergency_contact_phone' => '0777788990',
        ])]);

        Livewire::test(EmployeesIndex::class)
            ->call('openHireUpload')
            ->set('hireExcelFile', $upload)
            ->call('uploadHires')
            ->assertHasNoErrors()
            ->assertSet('hireImportErrors', [])
            ->assertSee('Success: 1');

        $employment = Employment::query()->where('company_id', $a->id)->latest('id')->first();
        $this->assertNotNull($employment);
        $assignment = $employment->assignmentOn('2026-07-01');
        $this->assertNotNull($assignment);
        $this->assertNull($employment->employee_category_id);
        $this->assertSame($shift->id, $employment->timeProfiles()->latest('id')->first()?->shift_id);
    }

    public function test_upload_does_not_require_wizard_fields(): void
    {
        $a = $this->company();
        $this->loginAs('hr-officer', $a);
        CompanyContext::set($a->id);

        $org = OrgUnit::factory()->create(['company_id' => $a->id, 'name' => 'Stores', 'is_active' => true]);
        $designation = Designation::factory()->create(['name' => 'Store Keeper', 'is_active' => true]);
        $location = Location::factory()->create(['name' => 'Depot', 'is_active' => true]);
        $shift = $this->shift(['name' => 'Store Shift', 'code' => 'STO01']);

        $upload = $this->makeHireUpload([[
            'passport_no' => 'PP-MIN-001',
            'full_name' => 'Ruwan Bandara',
            'org_unit' => $org->name,
            'designation' => $designation->name,
            'location' => $location->name,
            'shift' => $shift->name,
        ]]);

        Livewire::test(EmployeesIndex::class)
            ->call('openHireUpload')
            ->set('hireExcelFile', $upload)
            ->call('uploadHires')
            ->assertHasNoErrors()
            ->assertSet('hireImportErrors', [])
            ->assertSee('Success: 1');

        $employment = Employment::query()->where('company_id', $a->id)->latest('id')->first();
        $this->assertNotNull($employment);
        $this->assertSame('PERMANENT', $employment->type->value);
        $this->assertSame('Ruwan', $employment->person->first_name);
        $this->assertSame('Bandara', $employment->person->last_name);
        $this->assertSame('Ruwan', $employment->person->calling_name);
    }

    public function test_upload_accepts_permenent_type_and_city_fallback(): void
    {
        $a = $this->company();
        $this->loginAs('hr-officer', $a);
        CompanyContext::set($a->id);

        $org = OrgUnit::factory()->create(['company_id' => $a->id, 'name' => 'Cutting', 'is_active' => true]);
        $designation = Designation::factory()->create(['name' => 'Cutter', 'is_active' => true]);
        $location = Location::factory()->create(['name' => 'Site C', 'is_active' => true]);
        $shift = $this->shift(['name' => 'Mid Shift', 'code' => 'MID01']);

        $upload = $this->makeHireUpload([$this->baseHireRow([
            'passport_no' => 'PP-EXCEL-CITY',
            'first_name' => 'Kasun',
            'last_name' => 'Jayasuriya',
            'name_with_initials' => 'K. Jayasuriya',
            'calling_name' => 'Kasun',
            'date_of_birth' => '1993-06-12',
            'mobile' => '0774455667',
            'address_line_1' => '9 Temple Road',
            'city' => '',
            'district' => 'Gampaha',
            'type' => 'Permenent',
            'hire_date' => '2026-06-01',
            'org_unit' => $org->name,
            'designation' => $designation->name,
            'location' => $location->name,
            'shift' => $shift->name,
            'basic_salary' => '45000.00',
            'emergency_contact_name' => 'Contact',
            'emergency_contact_relationship' => 'Father',
            'emergency_contact_phone' => '0775566778',
        ])]);

        Livewire::test(EmployeesIndex::class)
            ->call('openHireUpload')
            ->set('hireExcelFile', $upload)
            ->call('uploadHires')
            ->assertHasNoErrors()
            ->assertSet('hireImportErrors', [])
            ->assertSee('Success: 1');

        $employment = Employment::query()->where('company_id', $a->id)->latest('id')->first();
        $this->assertNotNull($employment);
        $this->assertSame('PERMANENT', $employment->type->value);

        $address = $employment->person->addresses()->first();
        $this->assertNotNull($address);
        $this->assertSame('Gampaha', $address->city);
    }

    public function test_upload_hires_saves_extended_wizard_fields(): void
    {
        $a = $this->company();
        $this->loginAs('hr-officer', $a);
        CompanyContext::set($a->id);

        $org = OrgUnit::factory()->create(['company_id' => $a->id, 'name' => 'Finishing', 'is_active' => true]);
        $designation = Designation::factory()->create(['name' => 'Operator', 'is_active' => true]);
        $location = Location::factory()->create(['name' => 'Site B', 'is_active' => true]);
        $shift = $this->shift(['name' => 'General', 'code' => 'GEN01']);
        Region::factory()->create(['name' => 'Buddhism', 'is_active' => true, 'sort_order' => 1]);
        $deviceGroup = DeviceGroup::factory()->create(['code' => 'NIT', 'name' => 'Nittambuwa', 'is_active' => true]);
        $earning = PayComponent::factory()->create([
            'code' => 'FUELX',
            'name' => 'Fuel X',
            'kind' => ComponentKind::EARNING,
            'method' => CalcMethod::FIXED,
            'is_active' => true,
            'is_system' => false,
        ]);
        $deduction = PayComponent::factory()->create([
            'code' => 'HOSTELX',
            'name' => 'Hostel X',
            'kind' => ComponentKind::DEDUCTION,
            'method' => CalcMethod::FIXED,
            'is_active' => true,
            'is_system' => false,
        ]);

        $upload = $this->makeHireUpload([$this->baseHireRow([
            'passport_no' => 'PP-EXCEL-EXT',
            'first_name' => 'Amali',
            'last_name' => 'Fernando',
            'name_with_initials' => 'A. Fernando',
            'calling_name' => 'Amali',
            'full_name' => 'Amali S Fernando',
            'date_of_birth' => '1992-05-10',
            'driving_licence_no' => 'B1234567',
            'landline_no' => '0112345678',
            'office_no' => '0118765432',
            'region' => 'Buddhism',
            'arrive_work_from' => ArriveWorkFrom::HOME->value,
            'province' => 'Western',
            'electorate_district' => 'Colombo',
            'gn_division' => 'Sample GN',
            'distance_km' => '12.5',
            'mobile' => '0779988776',
            'address_line_1' => '88 Lake Road',
            'city' => 'Colombo',
            'hire_date' => '2026-05-01',
            'employee_no' => 'ECW EX99',
            'epf_no' => 'EPF-EX-99',
            'org_unit' => $org->name,
            'designation' => $designation->name,
            'location' => $location->name,
            'shift' => $shift->name,
            'hrmis_active' => 'Yes',
            'attendance_active' => 'Yes',
            'payroll_active' => 'Yes',
            'basic_salary' => '60000.00',
            'ot_rate_mode' => OtRateMode::CALCULATED->value,
            'attendance_type' => '',
            'earning_type' => $earning->code,
            'earning_amount' => '2500.00',
            'deduction_type' => $deduction->code,
            'deduction_amount' => '1500.00',
            'hostel_available' => 'Yes',
            'food_breakfast' => 'Yes',
            'food_lunch' => 'No',
            'food_dinner' => 'Yes',
            'emergency_contact_name' => 'Contact',
            'emergency_contact_relationship' => 'Sister',
            'emergency_contact_phone' => '0771122334',
            'device_group' => $deviceGroup->code,
            'fingerprint_id' => '42',
        ])]);

        Livewire::test(EmployeesIndex::class)
            ->call('openHireUpload')
            ->set('hireExcelFile', $upload)
            ->call('uploadHires')
            ->assertHasNoErrors()
            ->assertSet('hireImportErrors', [])
            ->assertSee('Success: 1');

        $employment = Employment::query()->where('company_id', $a->id)->where('employee_no', 'ECW EX99')->first();
        $this->assertNotNull($employment);
        $this->assertSame('EPF-EX-99', $employment->epf_no);
        $this->assertTrue($employment->hrmis_active);
        $this->assertTrue($employment->attendance_active);
        $this->assertTrue($employment->payroll_active);
        $this->assertTrue($employment->hostel_available);
        $this->assertTrue($employment->food_breakfast);
        $this->assertFalse($employment->food_lunch);
        $this->assertTrue($employment->food_dinner);

        $person = $employment->person;
        $this->assertSame('Amali S Fernando', $person->full_name);
        $this->assertSame('B1234567', $person->driving_licence_no);
        $this->assertSame('0112345678', $person->landline_no);
        $this->assertSame('0118765432', $person->office_no);
        $this->assertSame('Buddhism', $person->region);
        $this->assertSame(ArriveWorkFrom::HOME, $person->arrive_work_from);
        $this->assertSame('Western', $person->province);
        $this->assertSame('Colombo', $person->electorate_district);
        $this->assertSame('Sample GN', $person->gn_division);
        $this->assertSame('12.50', number_format((float) $person->distance_km, 2, '.', ''));

        $this->assertTrue(
            BiometricEnrolment::query()
                ->where('employment_id', $employment->id)
                ->where('device_group', 'NIT')
                ->where('device_user_id', 42)
                ->whereNull('valid_to')
                ->exists()
        );

        $this->assertTrue(
            EmployeeComponent::query()
                ->where('employment_id', $employment->id)
                ->where('pay_component_id', $earning->id)
                ->exists()
        );
        $this->assertTrue(
            EmployeeComponent::query()
                ->where('employment_id', $employment->id)
                ->where('pay_component_id', $deduction->id)
                ->exists()
        );
    }

    public function test_upload_continues_after_invalid_row(): void
    {
        $a = $this->company();
        $this->loginAs('hr-officer', $a);
        CompanyContext::set($a->id);

        $org = OrgUnit::factory()->create(['company_id' => $a->id, 'name' => 'Packing', 'is_active' => true]);
        $designation = Designation::factory()->create(['name' => 'Packer', 'is_active' => true]);
        $location = Location::factory()->create(['name' => 'Warehouse', 'is_active' => true]);
        $shift = $this->shift(['name' => 'Night Shift', 'code' => 'NGT01']);

        $good = $this->baseHireRow([
            'passport_no' => 'PP-EXCEL-002',
            'first_name' => 'Sunil',
            'last_name' => 'Silva',
            'name_with_initials' => 'S. Silva',
            'calling_name' => 'Sunil',
            'date_of_birth' => '1991-02-20',
            'mobile' => '0771112233',
            'address_line_1' => '45 Lake Road',
            'city' => 'Kandy',
            'hire_date' => '2026-04-01',
            'org_unit' => $org->name,
            'designation' => $designation->name,
            'location' => $location->name,
            'shift' => $shift->name,
            'basic_salary' => '40000.00',
            'emergency_contact_name' => 'Emergency Contact',
            'emergency_contact_relationship' => 'Brother',
            'emergency_contact_phone' => '0772223344',
        ]);

        $bad = $this->baseHireRow([
            'passport_no' => 'PP-EXCEL-003',
            'first_name' => 'Bad',
            'last_name' => 'Row',
            'name_with_initials' => 'B. Row',
            'calling_name' => 'Bad',
            'date_of_birth' => '1991-02-20',
            'mobile' => '0771112233',
            'address_line_1' => '45 Lake Road',
            'city' => 'Kandy',
            'hire_date' => '2026-04-01',
            'org_unit' => 'Missing Org Unit',
            'designation' => $designation->name,
            'location' => $location->name,
            'shift' => $shift->name,
            'basic_salary' => '40000.00',
            'emergency_contact_name' => 'Emergency Contact',
            'emergency_contact_relationship' => 'Brother',
            'emergency_contact_phone' => '0772223344',
        ]);

        $upload = $this->makeHireUpload([$bad, $good]);

        Livewire::test(EmployeesIndex::class)
            ->call('openHireUpload')
            ->set('hireExcelFile', $upload)
            ->call('uploadHires')
            ->assertSee('Success: 1')
            ->assertSee('Errors: 1');

        $this->assertSame(1, Employment::query()->where('company_id', $a->id)->count());
        $this->assertTrue(
            Employment::query()->where('company_id', $a->id)->whereHas('person', fn ($q) => $q->where('passport_no', 'PP-EXCEL-002'))->exists()
        );
    }

    public function test_upload_forbidden_without_create_permission(): void
    {
        $a = $this->company();
        $user = User::factory()->forCompanies([$a])->withPermissions('employee.view')->create([
            'must_change_password' => false,
        ]);
        $this->actingAs($user);
        CompanyContext::set($a->id);

        Livewire::test(EmployeesIndex::class)
            ->call('openHireUpload')
            ->assertForbidden();
    }

    /** @param  array<string, mixed>  $overrides */
    private function baseHireRow(array $overrides = []): array
    {
        return array_merge([
            'nic' => '',
            'passport_no' => '',
            'first_name' => '',
            'middle_name' => '',
            'last_name' => '',
            'name_with_initials' => '',
            'calling_name' => '',
            'full_name' => '',
            'date_of_birth' => '',
            'gender' => 'MALE',
            'marital_status' => 'SINGLE',
            'nationality' => 'Sri Lankan',
            'driving_licence_no' => '',
            'landline_no' => '',
            'office_no' => '',
            'region' => '',
            'arrive_work_from' => '',
            'province' => '',
            'electorate_district' => '',
            'gn_division' => '',
            'distance_km' => '',
            'mobile' => '',
            'email' => '',
            'address_line_1' => '',
            'address_line_2' => '',
            'city' => '',
            'district' => '',
            'postal_code' => '',
            'type' => 'PERMANENT',
            'hire_date' => '',
            'confirmation_due_date' => '',
            'resign_date' => '',
            'employee_no' => '',
            'epf_no' => '',
            'org_unit' => '',
            'designation' => '',
            'location' => '',
            'shift' => '',
            'employee_category' => '',
            'hrmis_active' => 'Yes',
            'attendance_active' => 'Yes',
            'payroll_active' => 'Yes',
            'basic_salary' => '',
            'ot_rate_mode' => 'CALCULATED',
            'ot_rate' => '',
            'attendance_type' => '',
            'earning_type' => '',
            'earning_amount' => '',
            'deduction_type' => '',
            'deduction_amount' => '',
            'hostel_available' => 'No',
            'food_breakfast' => 'No',
            'food_lunch' => 'No',
            'food_dinner' => 'No',
            'paid_in_cash' => 'Yes',
            'bank' => '',
            'branch' => '',
            'account_no' => '',
            'account_name' => '',
            'emergency_contact_name' => '',
            'emergency_contact_relationship' => '',
            'emergency_contact_phone' => '',
            'ot_mode' => 'NONE',
            'late_deduction' => 'No',
            'device_group' => '',
            'fingerprint_id' => '',
            'reports_to_employee_no' => '',
        ], $overrides);
    }

    /** @param  list<array<string, mixed>>  $assocRows */
    private function makeHireUpload(array $assocRows): UploadedFile
    {
        $headings = EmployeeHireTemplateExport::hireHeadings();
        $rows = [];
        foreach ($assocRows as $assoc) {
            $row = [];
            foreach ($headings as $heading) {
                $row[] = $assoc[$heading] ?? '';
            }
            $rows[] = $row;
        }

        $export = new class($rows, $headings) implements FromArray, WithHeadings
        {
            /** @param  list<list<mixed>>  $rows */
            /** @param  list<string>  $headings */
            public function __construct(private array $rows, private array $headings) {}

            public function headings(): array
            {
                return $this->headings;
            }

            public function array(): array
            {
                return $this->rows;
            }
        };

        $relative = 'testing/hires-'.uniqid('', true).'.xlsx';
        Excel::store($export, $relative, 'local');
        $path = Storage::disk('local')->path($relative);

        return UploadedFile::fake()->createWithContent('hires.xlsx', file_get_contents($path));
    }
}
