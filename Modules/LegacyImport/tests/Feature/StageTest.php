<?php

namespace Modules\LegacyImport\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\LegacyImport\Models\StgEmployee;
use Modules\LegacyImport\Tests\Support\LegacyFixtures;
use Tests\TestCase;

class StageTest extends TestCase
{
    use LegacyFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpLegacyFixtures();
    }

    public function test_only_active_employees_are_staged(): void
    {
        $keep = $this->legacyEmployee();
        $this->legacyEmployee(['is_resigned' => 1]);
        $this->legacyEmployee(['deleted' => 1]);
        $this->artisan('hrmis:import:stage')->assertSuccessful();
        $this->assertSame([$keep], StgEmployee::pluck('legacy_hrm_id')->all());
    }

    public function test_employee_number_formats_normalise_to_one(): void
    {
        $a = $this->legacyEmployee(['emp_id' => 'ECW 035']);
        $b = $this->legacyEmployee(['emp_id' => 'ecw_066']);
        $this->artisan('hrmis:import:stage');
        $this->assertSame('ECW035', StgEmployee::where('legacy_hrm_id', $a)->value('normalised_employee_no'));
        $this->assertSame('ECW066', StgEmployee::where('legacy_hrm_id', $b)->value('normalised_employee_no'));
    }

    public function test_payroll_profile_is_matched_despite_different_number_format(): void
    {
        $id = $this->legacyEmployee(['emp_id' => 'ECW_066', 'payroll' => false]);
        $this->legacyProfile(['emp_no' => 'ECW 066', 'epf_no' => '321', 'branch_id' => 1, 'status' => 1]);
        $this->artisan('hrmis:import:stage');
        $this->assertSame('321', StgEmployee::where('legacy_hrm_id', $id)->sole()->raw['payroll']['epf_no']);
    }

    public function test_missing_mapping_file_stops_with_a_clear_message(): void
    {
        $this->mappingFiles(['job_map.csv' => null]);
        $this->artisan('hrmis:import:stage')->expectsOutputToContain('job_map.csv')->assertFailed();
    }

    public function test_staging_never_writes_to_the_legacy_databases(): void
    {
        $this->legacyEmployee();
        DB::connection('legacy_hrm')->enableQueryLog();
        DB::connection('legacy_payroll')->enableQueryLog();
        $this->artisan('hrmis:import:stage');
        foreach (['legacy_hrm', 'legacy_payroll'] as $c) {
            foreach (DB::connection($c)->getQueryLog() as $q) {
                $this->assertStringStartsWith('select', strtolower(ltrim($q['query'])));
            }
        }
    }
}
