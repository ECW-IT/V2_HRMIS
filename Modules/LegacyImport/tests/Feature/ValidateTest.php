<?php

namespace Modules\LegacyImport\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\LegacyImport\Models\StgEmployee;
use Modules\LegacyImport\Models\StgIssue;
use Modules\LegacyImport\Services\MappingRepository;
use Modules\LegacyImport\Tests\Support\LegacyFixtures;
use Tests\TestCase;

class ValidateTest extends TestCase
{
    use LegacyFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpLegacyFixtures();
    }

    /** @return list<string> */
    private function codes(?int $legacyId = null): array
    {
        $this->artisan('hrmis:import:stage')->assertSuccessful();
        $this->artisan('hrmis:import:validate');

        $query = StgIssue::query();
        if ($legacyId !== null) {
            $query->where('legacy_hrm_id', $legacyId);
        }

        $codes = $query->pluck('code')->unique()->sort()->values()->all();

        return $codes;
    }

    public function test_clean_employee_has_no_issues(): void
    {
        $id = $this->legacyEmployee();
        $this->assertSame([], $this->codes($id));
        $this->artisan('hrmis:import:validate')->assertSuccessful();
    }

    public function test_imp001_two_active_rows_sharing_a_nic(): void
    {
        $a = $this->legacyEmployee(['emp_national_id' => '853400937V', 'emp_birthday' => '1985-12-05', 'emp_gender' => 'Male']);
        $b = $this->legacyEmployee(['emp_national_id' => '198534000937', 'emp_birthday' => '1985-12-05', 'emp_gender' => 'Male']);
        $this->assertContains('IMP001', $this->codes($a));
        $this->assertContains('IMP001', $this->codes($b));
        $this->artisan('hrmis:import:validate')->assertFailed();
    }

    public function test_imp002_birthday_disagreeing_with_nic_is_a_warning(): void
    {
        $id = $this->legacyEmployee(['emp_national_id' => '853400937V', 'emp_birthday' => '1985-12-06', 'emp_gender' => 'Male']);
        $this->assertSame(['IMP002'], $this->codes($id));
        $this->artisan('hrmis:import:validate')->assertSuccessful();
    }

    public function test_imp004_same_number_in_one_company(): void
    {
        $a = $this->legacyEmployee(['emp_id' => 'ECW 035']);
        $b = $this->legacyEmployee(['emp_id' => 'ECW035']);
        $this->assertContains('IMP004', $this->codes($a));
        $this->assertContains('IMP004', $this->codes($b));
    }

    public function test_same_number_in_two_companies_is_not_an_issue(): void
    {
        $this->mappingFiles(['company_map.csv' => "legacy_branch_id,company_code,location_code\n1,CO1,NIT\n2,CO2,NEG\n"]);
        $a = $this->legacyEmployee(['emp_id' => 'ECW 003', 'emp_branch' => 1]);
        $b = $this->legacyEmployee(['emp_id' => 'ECW 003', 'emp_branch' => 2]);
        $this->assertNotContains('IMP004', $this->codes($a));
        $this->assertNotContains('IMP004', $this->codes($b));
    }

    public function test_placeholder_epf_values_count_as_missing(): void
    {
        foreach (['.', '0', '', '-', null] as $v) {
            $this->createLegacyTables();
            $this->mappingFiles();
            $id = $this->legacyEmployee(['emp_etfno' => $v, 'payroll_epf' => $v]);
            $this->assertContains('IMP007', $this->codes($id), var_export($v, true));
        }
    }

    public function test_intern_without_epf_is_not_an_issue(): void
    {
        $id = $this->legacyEmployee(['emp_status' => 3, 'emp_etfno' => null, 'payroll_epf' => null]);
        $this->assertNotContains('IMP007', $this->codes($id));
    }

    public function test_imp008_hrm_and_payroll_epf_differ(): void
    {
        $id = $this->legacyEmployee(['emp_etfno' => '500', 'payroll_epf' => '501']);
        $this->assertContains('IMP008', $this->codes($id));
    }

    public function test_imp013_shared_fingerprint_id_in_one_device_group(): void
    {
        $a = $this->legacyEmployee(['emp_fp_id' => 116]);
        $b = $this->legacyEmployee(['emp_fp_id' => 116]);
        $this->assertContains('IMP013', $this->codes($a));
        $this->assertContains('IMP013', $this->codes($b));
    }

    public function test_imp012_fingerprint_id_zero_is_a_warning(): void
    {
        $id = $this->legacyEmployee(['emp_fp_id' => 0]);
        $this->assertSame(['IMP012'], $this->codes($id));
    }

    public function test_imp003_unmapped_branch(): void
    {
        $id = $this->legacyEmployee(['emp_branch' => 9, 'payroll' => false]);
        $this->legacyProfile(['emp_no' => 'ECW 9XX', 'epf_no' => '9', 'branch_id' => 9, 'status' => 1]);
        // Remap employee number for payroll match won't work without company map — IMP003 expected.
        $this->assertContains('IMP003', $this->codes($id));
    }

    public function test_imp005_missing_join_date(): void
    {
        $id = $this->legacyEmployee(['emp_join_date' => null]);
        $this->assertContains('IMP005', $this->codes($id));
    }

    public function test_imp006_unknown_employment_status(): void
    {
        $id = $this->legacyEmployee(['emp_status' => 7]);
        $this->assertContains('IMP006', $this->codes($id));
    }

    public function test_imp009_unmapped_department(): void
    {
        $id = $this->legacyEmployee(['emp_department' => 99]);
        $this->assertContains('IMP009', $this->codes($id));
    }

    public function test_imp010_unmapped_job_title(): void
    {
        $id = $this->legacyEmployee(['job_title_id' => 99]);
        $this->assertContains('IMP010', $this->codes($id));
    }

    public function test_imp011_missing_or_unknown_grade(): void
    {
        $id = $this->legacyEmployee();
        // Remove grade row for this employee.
        $path = config('legacy_import.grade_assignment');
        file_put_contents($path, "legacy_hrm_id,grade_code\n");
        app(MappingRepository::class)->forget();
        $this->assertContains('IMP011', $this->codes($id));

        $this->createLegacyTables();
        $this->mappingFiles();
        $id2 = $this->legacyEmployee();
        file_put_contents($path, "legacy_hrm_id,grade_code\n{$id2},ZZ\n");
        app(MappingRepository::class)->forget();
        $this->assertContains('IMP011', $this->codes($id2));
    }

    public function test_imp015_no_active_payroll_profile(): void
    {
        $id = $this->legacyEmployee(['payroll' => false]);
        $this->assertContains('IMP015', $this->codes($id));

        $this->createLegacyTables();
        $this->mappingFiles();
        $id2 = $this->legacyEmployee(['emp_id' => 'ECW 777', 'payroll' => false]);
        $this->legacyProfile(['emp_no' => 'ECW 777', 'status' => 2, 'branch_id' => 1, 'epf_no' => '1']);
        $this->assertContains('IMP015', $this->codes($id2));
    }

    public function test_imp016_invalid_or_empty_nic(): void
    {
        $a = $this->legacyEmployee(['emp_national_id' => '']);
        $this->assertContains('IMP016', $this->codes($a));

        $this->createLegacyTables();
        $this->mappingFiles();
        $b = $this->legacyEmployee(['emp_national_id' => '12345']);
        $this->assertContains('IMP016', $this->codes($b));
    }

    public function test_imp017_active_payroll_profile_without_hrm_employee(): void
    {
        $this->legacyProfile(['emp_no' => 'ECW 900', 'status' => 1, 'branch_id' => 1, 'epf_no' => '900']);
        $codes = $this->codes();
        $this->assertContains('IMP017', $codes);
        $issue = StgIssue::query()->where('code', 'IMP017')->first();
        $this->assertNull($issue->legacy_hrm_id);
        $this->assertStringContainsString('ECW 900', $issue->message);
    }

    public function test_section_specific_mapping_wins_over_department_mapping(): void
    {
        $this->mappingFiles([
            'org_unit_map.csv' => "legacy_department_id,legacy_section_id,company_code,org_unit_code\n10,,CO1,PROD\n10,55,CO1,PAINT\n",
            'org_units.csv' => "company_code,code,name,type,parent_code\nCO1,PROD,Production,DEPARTMENT,\nCO1,PAINT,Paint,SECTION,PROD\n",
        ]);
        $id = $this->legacyEmployee(['emp_department' => 10, 'section_id' => 55]);
        $this->artisan('hrmis:import:stage')->assertSuccessful();
        $this->artisan('hrmis:import:validate')->assertSuccessful();
        $this->assertNotContains('IMP009', StgIssue::query()->where('legacy_hrm_id', $id)->pluck('code')->all());
        // Staged org unit resolution is validated by absence of IMP009; loader uses maps.
        $maps = app(MappingRepository::class);
        $staged = StgEmployee::where('legacy_hrm_id', $id)->sole();
        $this->assertSame('PAINT', $maps->orgUnitCode(
            $staged->raw['emp_department'],
            $staged->raw['section_id'],
            $staged->company_code
        ));
    }

    public function test_validate_rewrites_issues_each_time(): void
    {
        $this->legacyEmployee(['emp_join_date' => null]);
        $this->artisan('hrmis:import:stage');
        $this->artisan('hrmis:import:validate');
        $first = StgIssue::count();
        $this->artisan('hrmis:import:validate');
        $this->assertSame($first, StgIssue::count());
    }
}
