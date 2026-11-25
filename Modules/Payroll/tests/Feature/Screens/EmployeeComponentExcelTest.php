<?php

namespace Modules\Payroll\Tests\Feature\Screens;

use App\Models\User;
use App\Support\Company\CompanyContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Facades\Excel;
use Modules\CoreHr\Livewire\EmployeesIndex;
use Modules\Payroll\Enums\OtRateMode;
use Modules\Payroll\Exports\EmployeeComponentsTemplateExport;
use Modules\Payroll\Imports\EmployeeComponentsImport;
use Modules\Payroll\Models\EmployeeCompensation;
use Modules\Payroll\Models\EmployeeComponent;
use Modules\Payroll\Models\PayComponent;
use Modules\Payroll\Services\EmployeeComponentExcelService;
use Tests\Support\HrFixtures;
use Tests\TestCase;

class EmployeeComponentExcelTest extends TestCase
{
    use HrFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->travelTo('2026-06-15');
    }

    /** @param  list<string>  $headings */
    private function makeFile(array $headings, array $rows): string
    {
        $export = new class($headings, $rows) implements FromArray, WithHeadings
        {
            /** @param  list<string>  $headings */
            public function __construct(private array $headings, private array $rows) {}

            public function headings(): array
            {
                return $this->headings;
            }

            public function array(): array
            {
                return $this->rows;
            }
        };

        $relative = 'testing/payroll-components-'.uniqid('', true).'.xlsx';
        Excel::store($export, $relative, 'local');

        return Storage::disk('local')->path($relative);
    }

    /** @return list<array{row: int, data: array<string, string>}> */
    private function importRows(string $path): array
    {
        $import = new EmployeeComponentsImport;
        Excel::import($import, $path);

        return $import->rows;
    }

    public function test_valid_wide_file_adds_performance_allowance(): void
    {
        $company = $this->company();
        $employment = $this->paid('2026-01-01', $company);

        $path = $this->makeFile(
            ['Emp no', 'Performance Allowance', 'Travel Allowance'],
            [
                [$employment->employee_no, '5000', ''],
            ]
        );

        $result = app(EmployeeComponentExcelService::class)->process(
            $company,
            $this->importRows($path),
            '2026-06-15'
        );

        $this->assertSame(1, $result['success_count']);
        $this->assertSame(0, $result['error_count']);
        $this->assertSame([], $result['errors']);

        $this->assertTrue(
            EmployeeComponent::query()
                ->where('employment_id', $employment->id)
                ->whereHas('payComponent', fn ($q) => $q->where('code', 'PERF'))
                ->where('amount', '5000.00')
                ->whereDate('effective_from', '2026-06-15')
                ->whereNull('effective_to')
                ->exists()
        );
    }

    public function test_legacy_columns_import_perf_fuel_hostel(): void
    {
        $company = $this->company();
        $employment = $this->paid('2026-01-01', $company);

        $path = $this->makeFile(
            ['Emp no', 'Performance Allowance', 'Fuel_Allowance', 'Hostel fees'],
            [
                [$employment->employee_no, '4000', '1500', '2000'],
            ]
        );

        $result = app(EmployeeComponentExcelService::class)->process(
            $company,
            $this->importRows($path),
            '2026-06-15'
        );

        $this->assertSame(1, $result['success_count']);
        $this->assertSame(0, $result['error_count']);

        foreach (['PERF' => '4000.00', 'FUEL' => '1500.00', 'HOSTEL' => '2000.00'] as $code => $amount) {
            $this->assertTrue(
                EmployeeComponent::query()
                    ->where('employment_id', $employment->id)
                    ->whereHas('payComponent', fn ($q) => $q->where('code', $code))
                    ->where('amount', $amount)
                    ->exists(),
                "Missing {$code} {$amount}"
            );
        }
    }

    public function test_temporary_allowance_attaches_temp_allow(): void
    {
        $company = $this->company();
        $employment = $this->paid('2026-01-01', $company);

        $path = $this->makeFile(
            ['Emp no', 'Temporary_Allowance'],
            [
                [$employment->employee_no, '3200'],
            ]
        );

        $result = app(EmployeeComponentExcelService::class)->process(
            $company,
            $this->importRows($path),
            '2026-06-15'
        );

        $this->assertSame(1, $result['success_count']);
        $this->assertTrue(
            EmployeeComponent::query()
                ->where('employment_id', $employment->id)
                ->whereHas('payComponent', fn ($q) => $q->where('code', 'TEMP_ALLOW'))
                ->where('amount', '3200.00')
                ->exists()
        );
    }

    public function test_ot_rate_sets_fixed_ot_rate(): void
    {
        $company = $this->company();
        $employment = $this->paid('2026-01-01', $company);

        $path = $this->makeFile(
            ['Emp no', 'OT Rate'],
            [
                [$employment->employee_no, '375'],
            ]
        );

        // Mid-month effective from must snap to the 1st for compensation changes.
        $result = app(EmployeeComponentExcelService::class)->process(
            $company,
            $this->importRows($path),
            '2026-06-15'
        );

        $this->assertSame(1, $result['success_count']);
        $this->assertSame(0, $result['error_count']);

        $comp = EmployeeCompensation::query()
            ->where('employment_id', $employment->id)
            ->effectiveOn('2026-06-01')
            ->first();

        $this->assertNotNull($comp);
        $this->assertSame(OtRateMode::FIXED, $comp->ot_rate_mode);
        $this->assertSame('375.00', (string) $comp->ot_rate);
        $this->assertSame('2026-06-01', CarbonImmutable::parse($comp->effective_from)->toDateString());
    }

    public function test_temporary_allowance_works_when_component_missing_from_db(): void
    {
        $company = $this->company();
        $employment = $this->paid('2026-01-01', $company);

        PayComponent::query()->where('code', 'TEMP_ALLOW')->delete();

        $path = $this->makeFile(
            ['Emp no', 'temporary_allowance'],
            [
                [$employment->employee_no, '1100'],
            ]
        );

        $result = app(EmployeeComponentExcelService::class)->process(
            $company,
            $this->importRows($path),
            '2026-06-15'
        );

        $this->assertSame(1, $result['success_count']);
        $this->assertSame(0, $result['error_count']);
        $this->assertTrue(
            EmployeeComponent::query()
                ->where('employment_id', $employment->id)
                ->whereHas('payComponent', fn ($q) => $q->where('code', 'TEMP_ALLOW'))
                ->where('amount', '1100.00')
                ->exists()
        );
    }

    public function test_soft_skip_columns_warn_and_still_save_others(): void
    {
        $company = $this->company();
        $employment = $this->paid('2026-01-01', $company);

        $path = $this->makeFile(
            ['Emp no', 'Attendance Allowance', 'Meals Deduction', 'Over time hours', 'Performance Allowance'],
            [
                [$employment->employee_no, '2500', '300', '12', '5000'],
            ]
        );

        $result = app(EmployeeComponentExcelService::class)->process(
            $company,
            $this->importRows($path),
            '2026-06-15'
        );

        $this->assertSame(1, $result['success_count']);
        $this->assertSame(0, $result['error_count']);
        $this->assertCount(3, $result['warnings']);
        $this->assertTrue(
            EmployeeComponent::query()
                ->where('employment_id', $employment->id)
                ->whereHas('payComponent', fn ($q) => $q->where('code', 'PERF'))
                ->where('amount', '5000.00')
                ->exists()
        );
    }

    public function test_empty_amount_is_skipped(): void
    {
        $company = $this->company();
        $employment = $this->paid('2026-01-01', $company);

        $path = $this->makeFile(
            ['Emp no', 'Performance Allowance', 'Travel Allowance'],
            [
                [$employment->employee_no, '', ''],
            ]
        );

        $result = app(EmployeeComponentExcelService::class)->process(
            $company,
            $this->importRows($path),
            '2026-06-15'
        );

        $this->assertSame(0, $result['success_count']);
        $this->assertSame(0, $result['error_count']);
        $this->assertSame(0, EmployeeComponent::query()->where('employment_id', $employment->id)->count());
    }

    public function test_zero_accommodation_amount_is_skipped_without_failing_row(): void
    {
        $company = $this->company();
        $employment = $this->paid('2026-01-01', $company);

        $path = $this->makeFile(
            ['Emp no', 'Accommodation Allowance', 'Performance Allowance'],
            [
                [$employment->employee_no, '0', '5000'],
            ]
        );

        $result = app(EmployeeComponentExcelService::class)->process(
            $company,
            $this->importRows($path),
            '2026-06-15'
        );

        $this->assertSame(1, $result['success_count']);
        $this->assertSame(0, $result['error_count']);
        $this->assertSame([], $result['errors']);
        $this->assertFalse(
            EmployeeComponent::query()
                ->where('employment_id', $employment->id)
                ->whereHas('payComponent', fn ($q) => $q->where('code', 'ACCOM'))
                ->exists()
        );
        $this->assertTrue(
            EmployeeComponent::query()
                ->where('employment_id', $employment->id)
                ->whereHas('payComponent', fn ($q) => $q->where('code', 'PERF'))
                ->where('amount', '5000.00')
                ->exists()
        );
    }

    public function test_unknown_employee_number_errors(): void
    {
        $company = $this->company();
        $this->paid('2026-01-01', $company);

        $path = $this->makeFile(
            ['Emp no', 'Performance Allowance'],
            [
                ['MISSING-999', '1000'],
            ]
        );

        $result = app(EmployeeComponentExcelService::class)->process(
            $company,
            $this->importRows($path),
            '2026-06-15'
        );

        $this->assertSame(0, $result['success_count']);
        $this->assertSame(1, $result['error_count']);
        $this->assertStringContainsString('Unknown employee number', $result['errors'][0]);
    }

    public function test_overlap_same_amount_skips(): void
    {
        $company = $this->company();
        $employment = $this->paid('2026-01-01', $company, [], [
            'PERF' => '5000.00',
        ]);

        $path = $this->makeFile(
            ['Emp no', 'permance allowance'],
            [
                [$employment->employee_no, '5000'],
            ]
        );

        $result = app(EmployeeComponentExcelService::class)->process(
            $company,
            $this->importRows($path),
            '2026-06-15'
        );

        $this->assertSame(1, $result['success_count']);
        $this->assertSame(0, $result['error_count']);
        $this->assertSame(
            1,
            EmployeeComponent::query()
                ->where('employment_id', $employment->id)
                ->whereHas('payComponent', fn ($q) => $q->where('code', 'PERF'))
                ->count()
        );
    }

    public function test_component_effective_before_hire_clamps_to_hire_date(): void
    {
        $company = $this->company();
        $employment = $this->paid('2026-03-15', $company);

        $path = $this->makeFile(
            ['Emp no', 'Performance Allowance'],
            [
                [$employment->employee_no, '4500'],
            ]
        );

        $result = app(EmployeeComponentExcelService::class)->process(
            $company,
            $this->importRows($path),
            '2026-01-01'
        );

        $this->assertSame(1, $result['success_count']);
        $this->assertSame(0, $result['error_count']);
        $this->assertTrue(
            EmployeeComponent::query()
                ->where('employment_id', $employment->id)
                ->whereHas('payComponent', fn ($q) => $q->where('code', 'PERF'))
                ->where('amount', '4500.00')
                ->whereDate('effective_from', '2026-03-15')
                ->exists()
        );
    }

    public function test_overlap_different_amount_updates_existing(): void
    {
        $company = $this->company();
        $employment = $this->paid('2026-01-01', $company, [], [
            'PERF' => '5000.00',
        ]);

        $path = $this->makeFile(
            ['Emp no', 'Performance Allowance'],
            [
                [$employment->employee_no, '8000'],
            ]
        );

        $result = app(EmployeeComponentExcelService::class)->process(
            $company,
            $this->importRows($path),
            '2026-06-15'
        );

        $this->assertSame(1, $result['success_count']);
        $this->assertSame(0, $result['error_count']);
        $this->assertTrue(
            EmployeeComponent::query()
                ->where('employment_id', $employment->id)
                ->whereHas('payComponent', fn ($q) => $q->where('code', 'PERF'))
                ->where('amount', '8000.00')
                ->whereDate('effective_from', '2026-06-15')
                ->whereNull('effective_to')
                ->exists()
        );
    }

    public function test_download_payroll_template_requires_manage_permission(): void
    {
        $company = $this->company();
        $user = User::factory()->forCompanies([$company])->withPermissions('employee.view')->create([
            'must_change_password' => false,
        ]);
        $this->actingAs($user);
        CompanyContext::set($company->id);

        Livewire::test(EmployeesIndex::class)
            ->call('downloadPayrollTemplate')
            ->assertForbidden();
    }

    public function test_download_payroll_template_for_hr_manager(): void
    {
        $company = $this->company();
        $this->paid('2026-01-01', $company);
        $this->loginAs('hr-manager', $company);
        CompanyContext::set($company->id);

        Excel::fake();

        Livewire::test(EmployeesIndex::class)
            ->call('downloadPayrollTemplate');

        Excel::assertDownloaded('employee_payroll_components_template.xlsx', function (EmployeeComponentsTemplateExport $export) {
            $headings = $export->headings();

            return $headings[0] === 'Emp no'
                && in_array('Fuel_Allowance', $headings, true)
                && in_array('Hostel fees', $headings, true)
                && in_array('Temporary_Allowance', $headings, true)
                && in_array('OT Rate', $headings, true)
                && in_array('Attendance Allowance', $headings, true)
                && in_array('Performance Allowance', $headings, true);
        });
    }

    public function test_upload_payroll_components_via_livewire(): void
    {
        $company = $this->company();
        $employment = $this->paid('2026-01-01', $company);
        $this->loginAs('hr-manager', $company);
        CompanyContext::set($company->id);

        $path = $this->makeFile(
            ['Emp no', 'Performance Allowance'],
            [
                [$employment->employee_no, '7500'],
            ]
        );

        $upload = UploadedFile::fake()->createWithContent('payroll.xlsx', file_get_contents($path));

        Livewire::test(EmployeesIndex::class)
            ->call('openPayrollUpload')
            ->set('payrollEffectiveFrom', '2026-06-15')
            ->set('payrollExcelFile', $upload)
            ->call('uploadPayrollComponents')
            ->assertHasNoErrors()
            ->assertSet('payrollImportErrors', [])
            ->assertSee('Success: 1');

        $this->assertTrue(
            EmployeeComponent::query()
                ->where('employment_id', $employment->id)
                ->whereHas('payComponent', fn ($q) => $q->where('code', 'PERF'))
                ->where('amount', '7500.00')
                ->exists()
        );
    }
}
