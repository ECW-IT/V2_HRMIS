<?php

namespace Modules\Payroll\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Calendar\Services\PayPeriodService;
use Modules\CoreHr\Readiness\ReadinessService;
use Modules\Payroll\Database\Seeders\PayComponentSeeder;
use Modules\Payroll\Database\Seeders\PayRuleSeeder;
use Modules\Payroll\Enums\OtRateMode;
use Modules\Payroll\Enums\PayRunStatus;
use Modules\Payroll\Exceptions\InvalidCompensation;
use Modules\Payroll\Models\EmployeeCompensation;
use Modules\Payroll\Models\EmployeeComponent;
use Modules\Payroll\Models\PayRun;
use Modules\Payroll\Services\CompensationService;
use Modules\Payroll\Services\PayRuleService;
use Tests\Support\HrFixtures;
use Tests\TestCase;

class CompensationServiceTest extends TestCase
{
    use HrFixtures;
    use RefreshDatabase;

    private CompensationService $svc;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PayComponentSeeder::class);
        $this->svc = app(CompensationService::class);
    }

    public function test_first_compensation_starts_on_the_hire_date(): void
    {
        $e = $this->hired('2026-03-10');
        $this->seed(PayRuleSeeder::class);

        $this->svc->set($e, '2026-03-10', [
            'basic_salary' => '60000.00',
            'ot_rate_mode' => OtRateMode::CALCULATED,
        ]);

        $this->assertSame('60000.00', $this->svc->on($e, '2026-03-10')->basic_salary);
    }

    public function test_later_change_must_start_on_the_first_of_a_month_and_closes_the_previous_row(): void
    {
        $e = $this->hired('2026-01-01');
        $this->seed(PayRuleSeeder::class);

        $this->svc->set($e, '2026-01-01', [
            'basic_salary' => '60000.00',
            'ot_rate_mode' => OtRateMode::CALCULATED,
        ]);

        try {
            $this->svc->set($e, '2026-07-15', [
                'basic_salary' => '65000.00',
                'ot_rate_mode' => OtRateMode::CALCULATED,
            ]);
            $this->fail('Expected InvalidCompensation');
        } catch (InvalidCompensation) {
            // expected
        }

        $this->svc->set($e, '2026-07-01', [
            'basic_salary' => '65000.00',
            'ot_rate_mode' => OtRateMode::CALCULATED,
        ]);

        $first = EmployeeCompensation::query()
            ->where('employment_id', $e->id)
            ->orderBy('effective_from')
            ->first();

        $this->assertSame('2026-06-30', $first->effective_to->toDateString());
        $this->assertSame('60000.00', $this->svc->on($e, '2026-06-30')->basic_salary);
        $this->assertSame('65000.00', $this->svc->on($e, '2026-07-01')->basic_salary);
    }

    public function test_intern_must_have_zero_basic_and_others_a_positive_basic(): void
    {
        $intern = $this->hired('2026-01-01', emp: ['type' => 'INTERN']);
        $other = $this->hired('2026-01-01');
        $this->seed(PayRuleSeeder::class);

        $this->expectException(InvalidCompensation::class);
        $this->svc->set($intern, '2026-01-01', [
            'basic_salary' => '1000.00',
            'ot_rate_mode' => OtRateMode::CALCULATED,
        ]);
    }

    public function test_intern_zero_basic_ok_and_others_refuse_zero(): void
    {
        $intern = $this->hired('2026-01-01', emp: ['type' => 'INTERN']);
        $other = $this->hired('2026-01-01');
        $this->seed(PayRuleSeeder::class);

        $row = $this->svc->set($intern, '2026-01-01', [
            'basic_salary' => '0.00',
            'ot_rate_mode' => OtRateMode::CALCULATED,
        ]);
        $this->assertSame('0.00', $row->basic_salary);

        $this->expectException(InvalidCompensation::class);
        $this->svc->set($other, '2026-01-01', [
            'basic_salary' => '0.00',
            'ot_rate_mode' => OtRateMode::CALCULATED,
        ]);
    }

    public function test_fixed_mode_needs_a_rate_and_calculated_mode_stores_none(): void
    {
        $e = $this->hired('2026-01-01');
        $this->seed(PayRuleSeeder::class);

        try {
            $this->svc->set($e, '2026-01-01', [
                'basic_salary' => '60000.00',
                'ot_rate_mode' => OtRateMode::FIXED,
            ]);
            $this->fail('Expected InvalidCompensation');
        } catch (InvalidCompensation) {
            // expected
        }

        $fixed = $this->svc->set($e, '2026-01-01', [
            'basic_salary' => '60000.00',
            'ot_rate_mode' => OtRateMode::FIXED,
            'ot_rate' => '250.00',
        ]);
        $this->assertSame('250.00', $fixed->ot_rate);

        $calc = $this->svc->set($e, '2026-07-01', [
            'basic_salary' => '60000.00',
            'ot_rate_mode' => OtRateMode::CALCULATED,
            'ot_rate' => '999.00',
        ]);
        $this->assertNull($calc->ot_rate);
    }

    public function test_calculated_ot_rate(): void
    {
        $c = $this->company();
        $this->seed(PayRuleSeeder::class);
        $rules = app(PayRuleService::class)->rulesFor($c, '2026-06-01');

        foreach (['60000.00' => '375.00', '45000.00' => '281.25', '50000.00' => '312.50', '33333.00' => '208.33'] as $basic => $rate) {
            $comp = new EmployeeCompensation(['basic_salary' => $basic, 'ot_rate_mode' => 'CALCULATED']);
            $this->assertSame($rate, $this->svc->otRate($comp, $rules));
        }

        $fixed = new EmployeeCompensation(['basic_salary' => '0.00', 'ot_rate_mode' => 'FIXED', 'ot_rate' => '250.00']);
        $this->assertSame('250.00', $this->svc->otRate($fixed, $rules));
    }

    public function test_unknown_attendance_tier_is_refused(): void
    {
        $e = $this->hired('2026-01-01');
        $this->seed(PayRuleSeeder::class);

        $this->expectException(InvalidCompensation::class);
        $this->svc->set($e, '2026-01-01', [
            'basic_salary' => '60000.00',
            'ot_rate_mode' => OtRateMode::CALCULATED,
            'attendance_tier_code' => 'T9',
        ]);
    }

    public function test_component_must_be_attachable(): void
    {
        $e = $this->hired('2026-01-01');
        $this->seed(PayRuleSeeder::class);
        $this->svc->set($e, '2026-01-01', [
            'basic_salary' => '60000.00',
            'ot_rate_mode' => OtRateMode::CALCULATED,
        ]);

        foreach (['MEALS', 'BASIC'] as $code) {
            try {
                $this->svc->addComponent($e, $code, '1000.00', '2026-01-01');
                $this->fail("Expected InvalidCompensation for {$code}");
            } catch (InvalidCompensation) {
                // expected
            }
        }

        $fuel = $this->svc->addComponent($e, 'FUEL', '5000.00', '2026-01-01');
        $this->assertSame('5000.00', $fuel->amount);
    }

    public function test_component_ranges_cannot_overlap(): void
    {
        $e = $this->hired('2026-01-01');
        $this->seed(PayRuleSeeder::class);
        $this->svc->set($e, '2026-01-01', [
            'basic_salary' => '60000.00',
            'ot_rate_mode' => OtRateMode::CALCULATED,
        ]);

        $first = $this->svc->addComponent($e, 'FUEL', '5000.00', '2026-01-01');

        try {
            $this->svc->addComponent($e, 'FUEL', '6000.00', '2026-06-01');
            $this->fail('Expected InvalidCompensation');
        } catch (InvalidCompensation $ex) {
            $this->assertStringContainsString('already set for those dates', $ex->getMessage());
        }

        $this->svc->endComponent($first, '2026-05-31');
        $this->svc->addComponent($e, 'FUEL', '6000.00', '2026-06-01');

        $this->assertSame('5000.00', $this->svc->componentsOn($e, '2026-05-31')->sole()->amount);
        $this->assertSame('6000.00', $this->svc->componentsOn($e, '2026-06-01')->sole()->amount);
    }

    public function test_components_on_returns_only_those_in_force_in_display_order(): void
    {
        $e = $this->hired('2026-01-01');
        $this->seed(PayRuleSeeder::class);
        $this->svc->set($e, '2026-01-01', [
            'basic_salary' => '60000.00',
            'ot_rate_mode' => OtRateMode::CALCULATED,
        ]);

        $this->svc->addComponent($e, 'FUEL', '5000.00', '2026-01-01');
        $this->svc->addComponent($e, 'PERF', '10000.00', '2026-01-01');
        $ended = $this->svc->addComponent($e, 'TRAVEL', '2000.00', '2026-01-01', '2026-03-31');

        $on = $this->svc->componentsOn($e, '2026-06-01');
        $this->assertSame(['PERF', 'FUEL'], $on->pluck('payComponent.code')->all());
        $this->assertFalse($on->contains(fn ($row) => $row->id === $ended->id));
    }

    public function test_amounts_are_masked_in_the_audit_log(): void
    {
        $e = $this->hired('2026-01-01');
        $this->seed(PayRuleSeeder::class);

        $row = $this->svc->set($e, '2026-01-01', [
            'basic_salary' => '60000.00',
            'ot_rate_mode' => OtRateMode::CALCULATED,
        ]);

        $this->assertStringStartsWith('***', $row->auditLogs()->where('event', 'CREATED')->sole()->new_values['basic_salary']);
    }

    public function test_hr013_fails_until_a_compensation_row_exists(): void
    {
        $e = $this->hired('2026-01-01');
        $this->seed(PayRuleSeeder::class);

        $codes = app(ReadinessService::class)->forEmployment($e, '2026-11-01')->pluck('code')->all();
        $this->assertContains('HR013', $codes);

        $this->svc->set($e, '2026-01-01', [
            'basic_salary' => '60000.00',
            'ot_rate_mode' => OtRateMode::CALCULATED,
        ]);

        $codes = app(ReadinessService::class)->forEmployment($e, '2026-11-01')->pluck('code')->all();
        $this->assertNotContains('HR013', $codes);
    }

    public function test_change_component_ends_and_adds_from_a_later_date(): void
    {
        $e = $this->hired('2026-01-01');
        $this->seed(PayRuleSeeder::class);
        $this->svc->set($e, '2026-01-01', [
            'basic_salary' => '60000.00',
            'ot_rate_mode' => OtRateMode::CALCULATED,
        ]);

        $fuel = $this->svc->addComponent($e, 'FUEL', '5000.00', '2026-01-01');
        $changed = $this->svc->changeComponent($fuel, '2026-06-01', '7500.00', 'Mid-year increase');

        $this->assertSame('2026-05-31', $fuel->fresh()->effective_to->toDateString());
        $this->assertSame('7500.00', $changed->amount);
        $this->assertSame('2026-06-01', $changed->effective_from->toDateString());
        $this->assertNull($changed->effective_to);
        $this->assertSame('5000.00', $this->svc->componentsOn($e, '2026-05-31')->sole()->amount);
        $this->assertSame('7500.00', $this->svc->componentsOn($e, '2026-06-01')->sole()->amount);
    }

    public function test_change_component_updates_amount_on_the_same_start_date(): void
    {
        $e = $this->hired('2026-01-01');
        $this->seed(PayRuleSeeder::class);
        $this->svc->set($e, '2026-01-01', [
            'basic_salary' => '60000.00',
            'ot_rate_mode' => OtRateMode::CALCULATED,
        ]);

        $fuel = $this->svc->addComponent($e, 'FUEL', '5000.00', '2026-01-01');
        $changed = $this->svc->changeComponent($fuel, '2026-01-01', '5500.00');

        $this->assertSame($fuel->id, $changed->id);
        $this->assertSame('5500.00', $changed->amount);
        $this->assertSame(1, EmployeeComponent::query()->where('employment_id', $e->id)->count());
    }

    public function test_change_component_refuses_a_locked_pay_period(): void
    {
        $e = $this->hired('2026-01-01');
        $this->seed(PayRuleSeeder::class);
        $this->svc->set($e, '2026-01-01', [
            'basic_salary' => '60000.00',
            'ot_rate_mode' => OtRateMode::CALCULATED,
        ]);

        $fuel = $this->svc->addComponent($e, 'FUEL', '5000.00', '2026-01-01');

        $period = app(PayPeriodService::class)->ensure($e->company, 2026, 6);
        PayRun::query()->create([
            'company_id' => $e->company_id,
            'pay_period_id' => $period->id,
            'status' => PayRunStatus::LOCKED,
            'employee_count' => 0,
        ]);

        $this->expectException(InvalidCompensation::class);
        $this->expectExceptionMessage('Compensation cannot change inside a locked pay period.');
        $this->svc->changeComponent($fuel, '2026-06-15', '7500.00');
    }
}
