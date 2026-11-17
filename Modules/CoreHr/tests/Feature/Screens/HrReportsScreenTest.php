<?php

namespace Modules\CoreHr\Tests\Feature\Screens;

use App\Models\User;
use App\Support\Company\CompanyContext;
use Database\Seeders\ApprovalDefinitionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;
use Modules\CoreHr\Enums\EmploymentType;
use Modules\CoreHr\Enums\ExitReason;
use Modules\CoreHr\Exports\HrReportExport;
use Modules\CoreHr\Livewire\HrReportsIndex;
use Modules\CoreHr\Services\EmploymentService;
use Modules\CoreHr\Services\PersonService;
use Modules\Leave\Database\Seeders\LeaveSeeder;
use Modules\Leave\Enums\LeavePortion;
use Modules\Leave\Models\LeaveType;
use Modules\Leave\Services\LeaveService;
use Modules\Payroll\Database\Seeders\PayComponentSeeder;
use Modules\Payroll\Database\Seeders\PayRuleSeeder;
use Modules\Payroll\Enums\OtRateMode;
use Modules\Payroll\Services\CompensationService;
use Tests\Support\HrFixtures;
use Tests\TestCase;

class HrReportsScreenTest extends TestCase
{
    use HrFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_screen_needs_employee_view(): void
    {
        $this->actingAs(User::factory()->allCompanies()->create(['must_change_password' => false]))
            ->get(route('employees.reports'))
            ->assertForbidden();
    }

    public function test_screen_is_limited_to_the_selected_company(): void
    {
        $a = $this->company();
        $b = $this->company();
        $inA = $this->hired('2026-01-01', $a);
        $inB = $this->hired('2026-01-01', $b);
        $this->loginAs('hr-officer', $a);
        CompanyContext::set($a->id);

        Livewire::test(HrReportsIndex::class)
            ->set('reportType', 'active')
            ->call('runReport')
            ->assertSee($inA->employee_no)
            ->assertDontSee($inB->employee_no);
    }

    public function test_active_and_inactive_lists(): void
    {
        $c = $this->company();
        $active = $this->hired('2026-01-01', $c);
        $inactive = app(EmploymentService::class)->suspend($this->hired('2026-01-01', $c), '2026-06-01', 'Hold');
        $this->loginAs('hr-manager', $c);
        CompanyContext::set($c->id);

        Livewire::test(HrReportsIndex::class)
            ->set('reportType', 'active')
            ->call('runReport')
            ->assertSee($active->employee_no)
            ->assertDontSee($inactive->employee_no)
            ->set('reportType', 'inactive')
            ->call('runReport')
            ->assertSee($inactive->employee_no)
            ->assertDontSee($active->employee_no);
    }

    public function test_resigned_and_recruitment_respect_date_range(): void
    {
        $c = $this->company();
        $svc = app(EmploymentService::class);
        $resigned = $svc->exit($this->hired('2026-01-01', $c), '2026-06-15', ExitReason::RESIGNATION);
        $svc->exit($this->hired('2026-01-01', $c), '2026-08-01', ExitReason::RESIGNATION);
        $newHire = $this->hired('2026-05-10', $c);

        $this->loginAs('hr-manager', $c);
        CompanyContext::set($c->id);

        Livewire::test(HrReportsIndex::class)
            ->set('reportType', 'resigned')
            ->set('dateFrom', '2026-06-01')
            ->set('dateTo', '2026-06-30')
            ->call('runReport')
            ->assertSee($resigned->employee_no)
            ->set('reportType', 'recruitment')
            ->set('dateFrom', '2026-05-01')
            ->set('dateTo', '2026-05-31')
            ->call('runReport')
            ->assertSee($newHire->employee_no);
    }

    public function test_summary_history_birthday_probation_electorate(): void
    {
        $c = $this->company();
        $person = app(PersonService::class)->create([
            'nic' => null,
            'passport_no' => 'NHRREP001',
            'first_name' => 'July',
            'last_name' => 'Born',
            'name_with_initials' => 'J. Born',
            'date_of_birth' => '1990-07-15',
            'gender' => 'MALE',
            'electorate' => 'Gampaha',
            'polling_division' => 'Attanagalla',
            'gn_division' => 'GN-01',
        ]);
        $e = $this->hired('2026-01-01', $c, [
            'type' => EmploymentType::PROBATION->value,
            'confirmation_due_date' => '2026-07-01',
        ], $person);

        $this->loginAs('hr-manager', $c);
        CompanyContext::set($c->id);

        Livewire::test(HrReportsIndex::class)
            ->set('reportType', 'summary')
            ->set('employmentId', $e->id)
            ->call('runReport')
            ->assertSee($e->employee_no)
            ->assertSee('Gampaha')
            ->set('reportType', 'history')
            ->set('employmentId', $e->id)
            ->call('runReport')
            ->assertSee('HIRE')
            ->set('reportType', 'birthdays')
            ->set('month', '2026-07')
            ->call('runReport')
            ->assertSee($e->employee_no)
            ->set('reportType', 'probation')
            ->set('month', '2026-07')
            ->call('runReport')
            ->assertSee($e->employee_no)
            ->set('reportType', 'electorate')
            ->call('runReport')
            ->assertSee('Gampaha')
            ->assertSee('Attanagalla');
    }

    public function test_salary_increment_report_lists_compensation_rows(): void
    {
        $this->seed(PayComponentSeeder::class);
        $this->seed(PayRuleSeeder::class);
        $c = $this->company();
        $e = $this->hired('2026-01-01', $c);
        app(CompensationService::class)->set($e, '2026-01-01', [
            'basic_salary' => '50000.00',
            'ot_rate_mode' => OtRateMode::CALCULATED,
            'change_reason' => 'Opening',
        ]);
        app(CompensationService::class)->set($e, '2026-06-01', [
            'basic_salary' => '55000.00',
            'ot_rate_mode' => OtRateMode::CALCULATED,
            'change_reason' => 'Annual increment',
        ]);

        $this->loginAs('hr-manager', $c);
        CompanyContext::set($c->id);

        Livewire::test(HrReportsIndex::class)
            ->set('reportType', 'increments')
            ->set('dateFrom', '2026-06-01')
            ->set('dateTo', '2026-06-30')
            ->call('runReport')
            ->assertSee('55,000.00')
            ->assertSee('Annual increment');
    }

    public function test_leave_applied_report_and_excel_downloads(): void
    {
        $this->seed(LeaveSeeder::class);
        $this->seed(ApprovalDefinitionSeeder::class);
        $c = $this->company();
        $e = $this->hired('2026-01-01', $c);
        $user = $this->loginAs('hr-manager', $c);
        CompanyContext::set($c->id);
        $type = LeaveType::query()->where('code', 'ML')->firstOrFail();
        app(LeaveService::class)->request($e, $type->id, '2026-12-10', LeavePortion::FULL, $user, ['pre_approved' => true]);

        Livewire::test(HrReportsIndex::class)
            ->set('reportType', 'leave')
            ->set('leaveMode', 'daily')
            ->set('leaveDate', '2026-12-10')
            ->call('runReport')
            ->assertSee($e->employee_no);

        Excel::fake();
        Livewire::test(HrReportsIndex::class)
            ->set('reportType', 'active')
            ->call('download');
        Excel::assertDownloaded('hr-report-active.xlsx', fn (HrReportExport $export) => true);

        Excel::fake();
        Livewire::test(HrReportsIndex::class)
            ->set('reportType', 'leave')
            ->set('leaveMode', 'range')
            ->set('dateFrom', '2026-12-01')
            ->set('dateTo', '2026-12-31')
            ->call('download');
        Excel::assertDownloaded('hr-report-leave.xlsx', fn (HrReportExport $export) => true);
    }
}
