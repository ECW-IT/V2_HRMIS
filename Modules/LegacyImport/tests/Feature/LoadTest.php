<?php

namespace Modules\LegacyImport\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Attendance\Models\BiometricEnrolment;
use Modules\Attendance\Services\EnrolmentService;
use Modules\CoreHr\Enums\EmploymentType;
use Modules\CoreHr\Enums\EventType;
use Modules\CoreHr\Enums\Gender;
use Modules\CoreHr\Models\Assignment;
use Modules\CoreHr\Models\DocumentType;
use Modules\CoreHr\Models\Employment;
use Modules\CoreHr\Models\Person;
use Modules\LegacyImport\Models\StgEmployee;
use Modules\LegacyImport\Tests\Support\LegacyFixtures;
use Modules\Organisation\Models\Company;
use Tests\TestCase;

class LoadTest extends TestCase
{
    use LegacyFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpLegacyFixtures();
        $this->seedDocumentTypes();
    }

    private function seedDocumentTypes(): void
    {
        foreach (require database_path('seeders/data/document_types.php') as $row) {
            DocumentType::query()->firstOrCreate(
                ['code' => $row['code']],
                [
                    'name' => $row['name'],
                    'requires_expiry' => $row['requires_expiry'],
                    'is_mandatory' => $row['is_mandatory'],
                ]
            );
        }
    }

    private function runImport(): void
    {
        $this->artisan('hrmis:import:stage')->assertSuccessful();
        $this->artisan('hrmis:import:validate')->assertSuccessful();
        $this->artisan('hrmis:import:load')->assertSuccessful();
    }

    public function test_load_refuses_while_errors_exist(): void
    {
        $this->legacyEmployee(['emp_join_date' => null]);
        $this->artisan('hrmis:import:stage');
        $this->artisan('hrmis:import:validate');
        $this->artisan('hrmis:import:load')->expectsOutputToContain('ERROR')->assertFailed();
        $this->assertSame(0, Employment::count());
    }

    public function test_clean_employee_is_loaded_through_the_services(): void
    {
        $id = $this->legacyEmployee([
            'emp_id' => 'ECW 035',
            'emp_national_id' => '853400937V',
            'emp_birthday' => '1985-12-05',
            'emp_gender' => 'Male',
            'emp_join_date' => '2024-03-01',
            'emp_status' => 1,
            'emp_etfno' => '500',
            'emp_fp_id' => 116,
            'emp_mobile' => '077-123 4567',
        ]);
        $this->runImport();

        $e = Employment::withoutGlobalScopes()->sole();
        $this->assertSame('ECW035', $e->employee_no);
        $this->assertSame('ECW 035', $e->legacy_employee_no);
        $this->assertSame('CO1', $e->company->code);
        $this->assertSame('198534000937', $e->person->nic);
        $this->assertSame('2024-03-01', $e->hire_date->toDateString());
        $this->assertSame(EmploymentType::PERMANENT, $e->type);
        $this->assertTrue($e->epf_liable);
        $this->assertSame('500', $e->epf_no);
        $a = $e->assignments()->sole();
        $this->assertSame('2024-03-01', $a->effective_from->toDateString());
        $this->assertSame('PROD', $a->orgUnit->code);
        $this->assertSame('TECH', $a->job->code);
        $this->assertSame('G3', $a->grade->code);
        $this->assertSame($e->id, app(EnrolmentService::class)->resolve('NIT', 116, '2026-12-01 08:00:00'));
        $this->assertSame('0771234567', $e->person->contacts()->where('type', 'MOBILE')->sole()->value);
        $this->assertSame(EventType::HIRE, $e->events()->sole()->type);
        $this->assertSame($e->id, StgEmployee::where('legacy_hrm_id', $id)->value('loaded_employment_id'));
    }

    public function test_nic_mismatch_uses_nic_values_and_sets_the_flag(): void
    {
        $this->legacyEmployee([
            'emp_national_id' => '853400937V',
            'emp_birthday' => '1985-12-06',
            'emp_gender' => 'Female',
        ]);
        $this->runImport();
        $p = Person::sole();
        $this->assertSame('1985-12-05', $p->date_of_birth->toDateString());
        $this->assertSame(Gender::MALE, $p->gender);
        $this->assertTrue($p->nic_mismatch);
    }

    public function test_employee_without_usable_epf_is_loaded_as_not_liable(): void
    {
        $this->legacyEmployee(['emp_etfno' => '.', 'payroll_epf' => '.']);
        // IMP007 is warning only — load allowed
        $this->runImport();
        $e = Employment::withoutGlobalScopes()->sole();
        $this->assertFalse($e->epf_liable);
        $this->assertNull($e->epf_no);
    }

    public function test_intern_is_loaded_as_intern_and_not_liable(): void
    {
        $this->legacyEmployee(['emp_status' => 3, 'emp_etfno' => null, 'payroll_epf' => null]);
        $this->runImport();
        $e = Employment::withoutGlobalScopes()->sole();
        $this->assertSame(EmploymentType::INTERN, $e->type);
        $this->assertFalse($e->epf_liable);
    }

    public function test_fingerprint_id_zero_creates_no_enrolment(): void
    {
        $this->legacyEmployee(['emp_fp_id' => 0]);
        $this->runImport();
        $this->assertSame(0, BiometricEnrolment::count());
    }

    public function test_next_employee_seq_is_set_above_the_highest_migrated_number(): void
    {
        $this->legacyEmployee(['emp_id' => 'ECW 035']);
        $this->legacyEmployee(['emp_id' => 'ECW 412']);
        $this->runImport();
        $this->assertSame(413, Company::where('code', 'CO1')->value('next_employee_seq'));
    }

    public function test_load_refuses_when_employees_already_exist(): void
    {
        $this->legacyEmployee();
        $this->runImport();
        $this->artisan('hrmis:import:load')->expectsOutputToContain('empty employee master')->assertFailed();
        $this->assertSame(1, Employment::withoutGlobalScopes()->count());
    }

    public function test_dry_run_saves_nothing(): void
    {
        $this->legacyEmployee();
        $this->artisan('hrmis:import:stage');
        $this->artisan('hrmis:import:validate');
        $this->artisan('hrmis:import:load', ['--dry-run' => true])->assertSuccessful();
        $this->assertSame(0, Employment::withoutGlobalScopes()->count());
        $this->assertSame(0, Person::count());
    }

    public function test_one_failing_employee_does_not_stop_the_others(): void
    {
        $good = $this->legacyEmployee();
        $bad = $this->legacyEmployee();
        $this->artisan('hrmis:import:stage');
        $this->artisan('hrmis:import:validate');
        StgEmployee::where('legacy_hrm_id', $bad)->update(['normalised_nic' => 'BROKEN']);
        $this->artisan('hrmis:import:load')->assertSuccessful();
        $this->assertSame(1, Employment::withoutGlobalScopes()->count());
        $this->assertNotNull(StgEmployee::where('legacy_hrm_id', $bad)->value('load_error'));
        $this->assertNull(StgEmployee::where('legacy_hrm_id', $bad)->value('loaded_employment_id'));
        $this->assertNotNull(StgEmployee::where('legacy_hrm_id', $good)->value('loaded_employment_id'));
    }

    public function test_import_counts_match_the_legacy_rows(): void
    {
        foreach (range(1, 5) as $i) {
            $this->legacyEmployee();
        }
        $this->runImport();
        $this->assertSame([5, 5, 5, 5], [
            Person::count(),
            Employment::withoutGlobalScopes()->count(),
            Assignment::count(),
            BiometricEnrolment::count(),
        ]);
        $this->assertSame(5, StgEmployee::whereNotNull('loaded_employment_id')->count());
    }
}
