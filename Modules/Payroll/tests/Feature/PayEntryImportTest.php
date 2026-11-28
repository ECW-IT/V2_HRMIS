<?php

namespace Modules\Payroll\Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Calendar\Services\PayPeriodService;
use Modules\Organisation\Models\Company;
use Modules\Payroll\Exceptions\InvalidPayEntry;
use Modules\Payroll\Models\PayEntry;
use Modules\Payroll\Services\PayEntryService;
use Tests\Support\HrFixtures;
use Tests\TestCase;

class PayEntryImportTest extends TestCase
{
    use HrFixtures;
    use RefreshDatabase;

    private PayEntryService $svc;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->svc = app(PayEntryService::class);
        $this->travelTo('2026-12-15');
    }

    private function manager($company): User
    {
        return User::factory()
            ->forCompanies([$company])
            ->withRole('payroll-officer')
            ->create(['must_change_password' => false]);
    }

    /** @param  list<array{0: string, 1: string, 2: ?string, 3: ?string, 4: ?string, 5: ?string}>  $rows */
    private function makeFile(array $rows): string
    {
        $export = new class($rows) implements FromArray, WithHeadings
        {
            /** @param list<array{0: string, 1: string, 2: ?string, 3: ?string, 4: ?string, 5: ?string}> $rows */
            public function __construct(private array $rows) {}

            public function headings(): array
            {
                return ['Employee no.', 'Component code', 'Quantity', 'Amount', 'Reference', 'Note'];
            }

            public function array(): array
            {
                return $this->rows;
            }
        };

        $relative = 'testing/pay-entries-'.uniqid('', true).'.xlsx';
        Excel::store($export, $relative, 'local');

        return Storage::disk('local')->path($relative);
    }

    public function test_valid_file_adds_every_row(): void
    {
        $e1 = $this->paid();
        $e2 = $this->paid(c: $e1->company);
        $e3 = $this->paid(c: $e1->company);
        $p = app(PayPeriodService::class)->ensure($e1->company, 2026, 12);
        $by = $this->manager($e1->company);

        $path = $this->makeFile([
            [$e1->employee_no, 'MEALS', null, '1000.00', null, null],
            [$e2->employee_no, 'MEALS', null, '2000.00', 'V1', null],
            [$e3->employee_no, 'MEALS', null, '3000.00', null, 'note'],
        ]);

        $result = $this->svc->import($p, $path, $by);

        $this->assertSame(['added' => 3, 'errors' => []], $result);
        $this->assertSame(3, PayEntry::query()->count());
    }

    public function test_one_bad_row_adds_nothing_and_reports_the_row(): void
    {
        $e1 = $this->paid();
        $e2 = $this->paid(c: $e1->company);
        $p = app(PayPeriodService::class)->ensure($e1->company, 2026, 12);
        $by = $this->manager($e1->company);

        $path = $this->makeFile([
            [$e1->employee_no, 'MEALS', null, '1000.00', null, null],
            [$e2->employee_no, 'MEALS', null, '2000.00', null, null],
            ['NO-SUCH-EMP', 'MEALS', null, '3000.00', null, null],
        ]);

        $result = $this->svc->import($p, $path, $by);

        $this->assertSame(0, $result['added']);
        $this->assertArrayHasKey(3, $result['errors']);
        $this->assertSame(0, PayEntry::query()->count());
    }

    public function test_employee_numbers_are_matched_in_the_periods_company_only(): void
    {
        $a = $this->paid();
        $otherCompany = Company::factory()->create();
        $b = $this->paid(c: $otherCompany);
        DB::table('employments')->where('id', $b->id)->update(['employee_no' => $a->employee_no]);

        $p = app(PayPeriodService::class)->ensure($a->company, 2026, 12);
        $by = $this->manager($a->company);

        $path = $this->makeFile([
            [$a->employee_no, 'MEALS', null, '1500.00', null, null],
        ]);

        $result = $this->svc->import($p, $path, $by);

        $this->assertSame(1, $result['added']);
        $this->assertSame($a->id, PayEntry::query()->sole()->employment_id);
        $this->assertSame(0, PayEntry::withoutGlobalScopes()->where('employment_id', $b->id)->count());
    }

    public function test_file_over_2000_rows_is_refused(): void
    {
        $e = $this->paid();
        $p = app(PayPeriodService::class)->ensure($e->company, 2026, 12);
        $by = $this->manager($e->company);

        $rows = [];
        for ($i = 0; $i < 2001; $i++) {
            $rows[] = [$e->employee_no, 'MEALS', null, '10.00', null, null];
        }

        $path = $this->makeFile($rows);

        $this->expectException(InvalidPayEntry::class);
        $this->svc->import($p, $path, $by);
    }
}
