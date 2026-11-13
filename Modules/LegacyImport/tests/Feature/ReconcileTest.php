<?php

namespace Modules\LegacyImport\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\CoreHr\Models\DocumentType;
use Modules\CoreHr\Models\EmployeeBankAccount;
use Modules\LegacyImport\Models\ImportRun;
use Modules\LegacyImport\Models\StgEmployee;
use Modules\LegacyImport\Services\Reconciler;
use Modules\LegacyImport\Tests\Support\LegacyFixtures;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class ReconcileTest extends TestCase
{
    use LegacyFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpLegacyFixtures();
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

    public function test_totals_balance_per_company(): void
    {
        $this->mappingFiles([
            'company_map.csv' => "legacy_branch_id,company_code,location_code\n1,CO1,NIT\n2,CO2,NEG\n",
            'org_unit_map.csv' => "legacy_department_id,legacy_section_id,company_code,org_unit_code\n10,,CO1,PROD\n10,,CO2,PROD\n",
            'org_units.csv' => "company_code,code,name,type,parent_code\nCO1,PROD,Production,DEPARTMENT,\nCO2,PROD,Production,DEPARTMENT,\n",
            'device_groups.csv' => "legacy_branch_id,device_group\n1,NIT\n2,NEG\n",
        ]);

        $c1a = $this->legacyEmployee(['emp_branch' => 1]);
        $c1b = $this->legacyEmployee(['emp_branch' => 1]);
        $c1c = $this->legacyEmployee(['emp_branch' => 1]);
        $this->legacyEmployee(['emp_branch' => 2]);
        $this->legacyEmployee(['emp_branch' => 2]);

        $this->artisan('hrmis:import:stage')->assertSuccessful();
        $this->artisan('hrmis:import:validate')->assertSuccessful();
        StgEmployee::where('legacy_hrm_id', $c1c)->update(['normalised_nic' => 'BROKEN']);
        $this->artisan('hrmis:import:load')->assertSuccessful();

        $result = app(Reconciler::class)->run();
        $summary = $result['summary'];

        $this->assertSame(3, $summary['CO1']['staged']);
        $this->assertSame(2, $summary['CO1']['loaded']);
        $this->assertSame(1, $summary['CO1']['failed']);
        $this->assertSame(2, $summary['CO2']['staged']);
        $this->assertSame(2, $summary['CO2']['loaded']);
        $this->assertSame(0, $summary['CO2']['failed']);
        $this->assertSame(5, $summary['TOTAL']['staged']);
        $this->assertSame(5, $summary['TOTAL']['legacy_active']);
    }

    public function test_report_file_has_the_four_sheets(): void
    {
        $this->legacyEmployee();
        $this->artisan('hrmis:import:stage');
        $this->artisan('hrmis:import:validate');
        $this->artisan('hrmis:import:load');
        $this->artisan('hrmis:import:report')->assertSuccessful();

        $runId = ImportRun::query()->orderByDesc('id')->value('id');
        $path = storage_path('app/private/import/reconciliation-'.$runId.'.xlsx');
        $this->assertFileExists($path);

        $spreadsheet = IOFactory::load($path);
        $titles = [];
        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $titles[] = $sheet->getTitle();
        }
        $this->assertSame(
            ['Summary', 'Issues', 'Load failures', 'Bank accounts to complete'],
            $titles
        );
    }

    public function test_bank_accounts_to_complete_sheet_lists_payroll_account_numbers(): void
    {
        $this->legacyEmployee(['bank_account' => '0012345678']);
        $this->artisan('hrmis:import:stage');
        $this->artisan('hrmis:import:validate');
        $this->artisan('hrmis:import:load');
        $this->artisan('hrmis:import:report');

        $this->assertSame(0, EmployeeBankAccount::count());

        $runId = ImportRun::query()->orderByDesc('id')->value('id');
        $path = storage_path('app/private/import/reconciliation-'.$runId.'.xlsx');
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getSheetByName('Bank accounts to complete');
        $rows = $sheet->toArray();
        $this->assertSame(['Employee no.', 'Name', 'Account no.'], $rows[0]);
        $this->assertSame('0012345678', $rows[1][2]);
        $this->assertNotSame('', $rows[1][0]);
        $this->assertNotSame('', $rows[1][1]);
    }

    public function test_report_includes_readiness_blockers_after_load(): void
    {
        $this->legacyEmployee();
        $this->artisan('hrmis:import:stage');
        $this->artisan('hrmis:import:validate');
        $this->artisan('hrmis:import:load');
        $result = app(Reconciler::class)->run();

        $this->assertSame(
            StgEmployee::whereNotNull('loaded_employment_id')->count(),
            $result['readiness']['CO1']['HR004'] ?? 0
        );
    }
}
