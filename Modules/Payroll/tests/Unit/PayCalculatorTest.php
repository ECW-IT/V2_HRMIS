<?php

namespace Modules\Payroll\Tests\Unit;

use Modules\Payroll\Calculator\ComponentSpec;
use Modules\Payroll\Calculator\PayCalculator;
use Modules\Payroll\Calculator\PayInput;
use Modules\Payroll\Calculator\PayItem;
use Modules\Payroll\Calculator\TierSpec;
use Modules\Payroll\Enums\CalcMethod;
use Modules\Payroll\Enums\ComponentKind;
use Modules\Payroll\Enums\ExtraDayMethod;
use Modules\Payroll\Exceptions\PayCalculationError;
use Modules\Payroll\Rules\PayRules;
use PHPUnit\Framework\TestCase;

class PayCalculatorTest extends TestCase
{
    private PayCalculator $calc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calc = new PayCalculator;
    }

    private function rules(): PayRules
    {
        return new PayRules(
            epfEmployeePercent: '8.00',
            epfEmployerPercent: '12.00',
            etfPercent: '3.00',
            taxSlabs: [
                ['width' => '150000.00', 'percent' => '0.00'],
                ['width' => '83333.33', 'percent' => '6.00'],
                ['width' => '41666.67', 'percent' => '18.00'],
                ['width' => '41666.67', 'percent' => '24.00'],
                ['width' => '41666.67', 'percent' => '30.00'],
                ['width' => null, 'percent' => '36.00'],
            ],
            lateDivisor: 14400,
            attendanceMinOffset: '2.0',
            attendanceMaxOffset: '3.0',
            hoursPerMonth: 240,
            hoursPerDay: 8,
            otMultiplier: '1.50',
        );
    }

    /** @return array<string, ComponentSpec> */
    private function components(): array
    {
        $rows = [
            ['BASIC', ComponentKind::EARNING, CalcMethod::EARNED_BASIC, 1, true, true, 10, null],
            ['OT', ComponentKind::EARNING, CalcMethod::OT, 1, false, true, 20, null],
            ['EXTRA_DAY', ComponentKind::EARNING, CalcMethod::EXTRA_DAY, 1, false, true, 30, null],
            ['ATT_ALLOW', ComponentKind::EARNING, CalcMethod::ATTENDANCE_TIER, 1, false, true, 40, null],
            ['LATE', ComponentKind::DEDUCTION, CalcMethod::LATE, 1, false, false, 500, null],
            ['EPF_EE', ComponentKind::DEDUCTION, CalcMethod::EPF_EMPLOYEE, 1, false, false, 510, null],
            ['APIT', ComponentKind::DEDUCTION, CalcMethod::TAX, 1, false, false, 520, null],
            ['CF', ComponentKind::DEDUCTION, CalcMethod::CARRIED_FORWARD, 1, false, false, 530, null],
            ['ADVANCE', ComponentKind::DEDUCTION, CalcMethod::ADVANCE, 2, false, false, 540, null],
            ['LOAN', ComponentKind::DEDUCTION, CalcMethod::LOAN, 1, false, false, 550, null],
            ['EPF_ER', ComponentKind::EMPLOYER, CalcMethod::EPF_EMPLOYER, 1, false, false, 900, null],
            ['ETF_ER', ComponentKind::EMPLOYER, CalcMethod::ETF, 1, false, false, 910, null],
            ['PERF', ComponentKind::EARNING, CalcMethod::PROPORTIONAL, 1, false, true, 100, null],
            ['TRAVEL', ComponentKind::EARNING, CalcMethod::PROPORTIONAL, 1, false, true, 110, null],
            ['INCENTIVE_1', ComponentKind::EARNING, CalcMethod::PROPORTIONAL, 1, false, true, 120, null],
            ['INCENTIVE_2', ComponentKind::EARNING, CalcMethod::PROPORTIONAL, 1, false, true, 130, null],
            ['FUEL', ComponentKind::EARNING, CalcMethod::FIXED, 1, false, true, 140, null],
            ['MOBILE', ComponentKind::EARNING, CalcMethod::FIXED, 1, false, true, 150, null],
            ['VEHICLE', ComponentKind::EARNING, CalcMethod::FIXED, 1, false, true, 160, null],
            ['ACCOM', ComponentKind::EARNING, CalcMethod::FIXED, 1, false, true, 170, null],
            ['SPECIAL', ComponentKind::EARNING, CalcMethod::FIXED, 1, false, true, 180, null],
            ['INTERN_ALLOW', ComponentKind::EARNING, CalcMethod::FIXED, 1, false, true, 190, null],
            ['VIP_JOBS', ComponentKind::EARNING, CalcMethod::QUANTITY_RATE, 1, false, true, 200, null],
            ['NIGHT_5', ComponentKind::EARNING, CalcMethod::NIGHT_UNITS, 2, false, true, 300, 5],
            ['NIGHT_3', ComponentKind::EARNING, CalcMethod::NIGHT_UNITS, 2, false, true, 310, 3],
            ['PIECE_RATE', ComponentKind::EARNING, CalcMethod::ENTRY, 2, false, true, 320, null],
            ['SALES_BONUS', ComponentKind::EARNING, CalcMethod::ENTRY, 2, false, true, 330, null],
            ['MEALS', ComponentKind::DEDUCTION, CalcMethod::ENTRY, 1, false, false, 600, null],
            ['HOSTEL', ComponentKind::DEDUCTION, CalcMethod::FIXED, 1, false, false, 610, null],
            ['SAVINGS', ComponentKind::DEDUCTION, CalcMethod::FIXED, 1, false, false, 620, null],
            ['DEPOSIT', ComponentKind::DEDUCTION, CalcMethod::FIXED, 1, false, false, 630, null],
            ['WELFARE', ComponentKind::DEDUCTION, CalcMethod::FIXED, 1, false, false, 640, null],
            ['OTHER_DED', ComponentKind::DEDUCTION, CalcMethod::ENTRY, 1, false, false, 690, null],
        ];

        $out = [];
        foreach ($rows as [$code, $kind, $method, $sheet, $epf, $tax, $order, $night]) {
            $out[$code] = new ComponentSpec($code, $kind, $method, $sheet, $epf, $tax, $order, $night);
        }

        return $out;
    }

    private function input(array $o = []): PayInput
    {
        $defaults = [
            'basicSalary' => '60000.00',
            'expectedDays' => '25.00',
            'creditedDays' => '25.00',
            'extraDays' => '0.00',
            'lateMinutesChargeable' => 0,
            'otMinutes' => 0,
            'epfLiable' => true,
            'otRate' => '375.00',
            'extraDayMethod' => ExtraDayMethod::HOURLY,
            'tier' => null,
            'rules' => $this->rules(),
            'components' => $this->components(),
            'items' => [],
            'carriedForwardIn' => '0.00',
            'advances' => [],
            'loanInstalments' => [],
            'overrides' => [],
        ];

        $m = array_merge($defaults, $o);

        return new PayInput(
            basicSalary: $m['basicSalary'],
            expectedDays: $m['expectedDays'],
            creditedDays: $m['creditedDays'],
            extraDays: $m['extraDays'],
            lateMinutesChargeable: $m['lateMinutesChargeable'],
            otMinutes: $m['otMinutes'],
            epfLiable: $m['epfLiable'],
            otRate: $m['otRate'],
            extraDayMethod: $m['extraDayMethod'],
            tier: $m['tier'],
            rules: $m['rules'],
            components: $m['components'],
            items: $m['items'],
            carriedForwardIn: $m['carriedForwardIn'],
            advances: $m['advances'],
            loanInstalments: $m['loanInstalments'],
            overrides: $m['overrides'],
        );
    }

    private function item(string $code, ?string $amount = null, ?string $quantity = null): PayItem
    {
        return new PayItem($code, $amount, $quantity);
    }

    private function standardExample(array $extra = []): PayInput
    {
        return $this->input(array_merge([
            'creditedDays' => '24.00',
            'lateMinutesChargeable' => 30,
            'otMinutes' => 600,
            'tier' => new TierSpec('5000.00', '1000.00'),
            'items' => [
                $this->item('PERF', '10000.00'),
                $this->item('FUEL', '5000.00'),
                $this->item('MEALS', '3000.00'),
            ],
            'advances' => [new PayItem('ADVANCE', '10000.00', null, 'salary_advance', 7)],
            'loanInstalments' => [new PayItem('LOAN', '5000.00', null, 'loan_instalment', 9)],
        ], $extra));
    }

    public function test_full_month_pays_full_basic_and_statutory(): void
    {
        $r = $this->calc->calculate($this->input());
        $this->assertSame('60000.00', $r->amount('BASIC'));
        $this->assertSame(['4800.00', '7200.00', '1800.00'], [$r->amount('EPF_EE'), $r->amount('EPF_ER'), $r->amount('ETF_ER')]);
        $this->assertSame(['60000.00', '0.00', '60000.00'], [$r->grossSheet1, $r->grossSheet2, $r->gross]);
        $this->assertSame(['4800.00', '55200.00', '0.00'], [$r->totalDeductions, $r->netPay, $r->carriedForwardOut]);
        $this->assertSame(['60000.00', '60000.00', '9000.00'], [$r->epfBase, $r->taxablePay, $r->employerTotal]);
        $this->assertNull($r->line('APIT'));
        $this->assertNull($r->line('OT'));
    }

    public function test_standard_example(): void
    {
        $r = $this->calc->calculate($this->standardExample());
        $this->assertSame('57600.00', $r->amount('BASIC'));
        $this->assertSame('3750.00', $r->amount('OT'));
        $this->assertSame('4000.00', $r->amount('ATT_ALLOW'));
        $this->assertSame('9600.00', $r->amount('PERF'));
        $this->assertSame('5000.00', $r->amount('FUEL'));
        $this->assertSame('79950.00', $r->gross);
        $this->assertSame('125.00', $r->amount('LATE'));
        $this->assertSame(['4608.00', '6912.00', '1728.00'], [$r->amount('EPF_EE'), $r->amount('EPF_ER'), $r->amount('ETF_ER')]);
        $this->assertSame('79825.00', $r->taxablePay);
        $this->assertSame('22733.00', $r->totalDeductions);
        $this->assertSame('57217.00', $r->netPay);
        $this->assertSame(['salary_advance', 7], [$r->line('ADVANCE')->sourceType, $r->line('ADVANCE')->sourceId]);
        $this->assertSame(
            ['BASIC', 'OT', 'ATT_ALLOW', 'PERF', 'FUEL', 'LATE', 'EPF_EE', 'ADVANCE', 'LOAN', 'MEALS', 'EPF_ER', 'ETF_ER'],
            array_map(fn ($l) => $l->code, $r->lines),
        );
    }

    public function test_rounding_example(): void
    {
        $r = $this->calc->calculate($this->input([
            'basicSalary' => '45000.00',
            'expectedDays' => '26.00',
            'creditedDays' => '23.50',
            'lateMinutesChargeable' => 37,
            'otMinutes' => 455,
            'otRate' => '281.25',
            'items' => [$this->item('PERF', '10000.00')],
        ]));
        $this->assertSame('40673.08', $r->amount('BASIC'));
        $this->assertSame('9038.46', $r->amount('PERF'));
        $this->assertSame('2132.81', $r->amount('OT'));
        $this->assertSame('51844.35', $r->gross);
        $this->assertSame('115.63', $r->amount('LATE'));
        $this->assertSame(['3253.85', '4880.77', '1220.19'], [$r->amount('EPF_EE'), $r->amount('EPF_ER'), $r->amount('ETF_ER')]);
        $this->assertSame('48474.87', $r->netPay);
    }

    public function test_extra_day_payment_by_method(): void
    {
        $two = ['creditedDays' => '27.00', 'extraDays' => '2.00'];
        $this->assertSame('6000.00', $this->calc->calculate($this->input($two + ['extraDayMethod' => ExtraDayMethod::HOURLY]))->amount('EXTRA_DAY'));
        $this->assertSame('7200.00', $this->calc->calculate($this->input($two + ['extraDayMethod' => ExtraDayMethod::DAILY]))->amount('EXTRA_DAY'));
        $this->assertSame('4000.00', $this->calc->calculate($this->input($two + ['basicSalary' => '0.00', 'otRate' => '250.00', 'epfLiable' => false, 'extraDayMethod' => null]))->amount('EXTRA_DAY'));

        $half = ['basicSalary' => '45000.00', 'expectedDays' => '26.00', 'creditedDays' => '27.50', 'extraDays' => '1.50'];
        $this->assertSame('3375.00', $this->calc->calculate($this->input($half + ['extraDayMethod' => ExtraDayMethod::HOURLY]))->amount('EXTRA_DAY'));
        $this->assertSame('3894.23', $this->calc->calculate($this->input($half + ['extraDayMethod' => ExtraDayMethod::DAILY]))->amount('EXTRA_DAY'));
    }

    public function test_extra_days_with_a_basic_but_no_method_is_an_error(): void
    {
        $this->expectException(PayCalculationError::class);
        $this->calc->calculate($this->input([
            'creditedDays' => '27.00',
            'extraDays' => '2.00',
            'extraDayMethod' => null,
        ]));
    }

    public function test_extra_days_do_not_raise_basic_or_epf(): void
    {
        $r = $this->calc->calculate($this->input([
            'creditedDays' => '27.00',
            'extraDays' => '2.00',
        ]));
        $this->assertSame('60000.00', $r->amount('BASIC'));
        $this->assertSame('4800.00', $r->amount('EPF_EE'));
    }

    public function test_attendance_allowance_window(): void
    {
        $t1 = new TierSpec('5000.00', '1000.00');
        foreach ([
            '22.50' => '0.00',
            '23.00' => '3000.00',
            '23.50' => '3000.00',
            '24.00' => '4000.00',
            '25.00' => '5000.00',
            '27.00' => '7000.00',
            '30.00' => '8000.00',
        ] as $credited => $expected) {
            $extra = max(0, (float) $credited - 25);
            $r = $this->calc->calculate($this->input([
                'creditedDays' => $credited,
                'extraDays' => number_format($extra, 2, '.', ''),
                'tier' => $t1,
            ]));
            $this->assertSame($expected, $r->amount('ATT_ALLOW'), "credited $credited");
        }
    }

    public function test_attendance_allowance_with_half_day_expected(): void
    {
        $t2 = new TierSpec('10000.00', '1100.00');
        $in = fn (string $c, string $x) => $this->input([
            'expectedDays' => '21.50',
            'creditedDays' => $c,
            'extraDays' => $x,
            'tier' => $t2,
        ]);
        $this->assertSame('10550.00', $this->calc->calculate($in('22.00', '0.50'))->amount('ATT_ALLOW'));
        $this->assertSame('7250.00', $this->calc->calculate($in('19.50', '0.00'))->amount('ATT_ALLOW'));
        $this->assertSame('0.00', $this->calc->calculate($in('19.00', '0.00'))->amount('ATT_ALLOW'));
    }

    public function test_no_tier_means_no_attendance_allowance(): void
    {
        $r = $this->calc->calculate($this->input(['tier' => null]));
        $this->assertNull($r->line('ATT_ALLOW'));
        $this->assertSame('0.00', $r->amount('ATT_ALLOW'));
    }

    public function test_fixed_items_ignore_attendance_and_proportional_items_follow_it(): void
    {
        $short = $this->calc->calculate($this->input([
            'creditedDays' => '24.00',
            'items' => [$this->item('FUEL', '5000.00'), $this->item('TRAVEL', '8000.00')],
        ]));
        $this->assertSame('5000.00', $short->amount('FUEL'));
        $this->assertSame('7680.00', $short->amount('TRAVEL'));

        $full = $this->calc->calculate($this->input([
            'creditedDays' => '27.00',
            'extraDays' => '2.00',
            'items' => [$this->item('TRAVEL', '8000.00')],
        ]));
        $this->assertSame('8000.00', $full->amount('TRAVEL'));
    }

    public function test_quantity_rate_item(): void
    {
        $r = $this->calc->calculate($this->input([
            'items' => [$this->item('VIP_JOBS', '500.00', '6')],
        ]));
        $this->assertSame('3000.00', $r->amount('VIP_JOBS'));
        $this->assertSame('6.00', $r->line('VIP_JOBS')->quantity);
        $this->assertSame('500.00', $r->line('VIP_JOBS')->rate);

        $none = $this->calc->calculate($this->input([
            'items' => [$this->item('VIP_JOBS', '500.00', null)],
        ]));
        $this->assertNull($none->line('VIP_JOBS'));
    }

    public function test_night_shift_units_and_short_month_reduction(): void
    {
        $night = fn (string $credited) => $this->calc->calculate($this->input([
            'creditedDays' => $credited,
            'items' => [$this->item('NIGHT_5', '800.00', '20')],
        ]))->amount('NIGHT_5');
        $this->assertSame('3200.00', $night('25.00'));
        $this->assertSame('2400.00', $night('24.00'));
        $this->assertSame('1600.00', $night('23.00'));
        $this->assertSame('1600.00', $night('21.00'));
    }

    public function test_reduction_applies_to_the_first_night_component_only(): void
    {
        $both = $this->calc->calculate($this->input([
            'creditedDays' => '23.00',
            'items' => [
                $this->item('NIGHT_5', '800.00', '20'),
                $this->item('NIGHT_3', '500.00', '12'),
            ],
        ]));
        $this->assertSame(['1600.00', '2000.00'], [$both->amount('NIGHT_5'), $both->amount('NIGHT_3')]);

        $only3 = $this->calc->calculate($this->input([
            'creditedDays' => '23.00',
            'items' => [$this->item('NIGHT_3', '500.00', '12')],
        ]));
        $this->assertSame('1000.00', $only3->amount('NIGHT_3'));
    }

    public function test_night_components_are_on_sheet_2_and_outside_the_epf_base(): void
    {
        $r = $this->calc->calculate($this->input([
            'items' => [$this->item('NIGHT_5', '800.00', '20')],
        ]));
        $this->assertSame(['60000.00', '3200.00', '60000.00', '63200.00'], [
            $r->grossSheet1,
            $r->grossSheet2,
            $r->epfBase,
            $r->taxablePay,
        ]);
    }

    public function test_night_hours_are_taken_out_of_ot_hours(): void
    {
        $r = $this->calc->calculate($this->input([
            'otMinutes' => 1800,
            'items' => [$this->item('NIGHT_5', '800.00', '20')],
        ]));
        $this->assertSame('10.00', $r->line('OT')->quantity);
        $this->assertSame('3750.00', $r->amount('OT'));

        $none = $this->calc->calculate($this->input([
            'otMinutes' => 600,
            'items' => [$this->item('NIGHT_5', '800.00', '20')],
        ]));
        $this->assertNull($none->line('OT'));
    }

    public function test_employee_not_epf_liable_has_no_epf_lines(): void
    {
        $r = $this->calc->calculate($this->input(['epfLiable' => false]));
        $this->assertNull($r->line('EPF_EE'));
        $this->assertNull($r->line('EPF_ER'));
        $this->assertNull($r->line('ETF_ER'));
        $this->assertSame('0.00', $r->epfBase);
        $this->assertSame('60000.00', $r->netPay);
    }

    public function test_intern_is_paid_by_allowance_only(): void
    {
        $r = $this->calc->calculate($this->input([
            'basicSalary' => '0.00',
            'epfLiable' => false,
            'extraDayMethod' => null,
            'items' => [$this->item('INTERN_ALLOW', '25000.00')],
        ]));
        $this->assertNull($r->line('BASIC'));
        $this->assertSame('25000.00', $r->gross);
        $this->assertSame('25000.00', $r->netPay);
    }

    public function test_tax_and_sheet_2(): void
    {
        $r = $this->calc->calculate($this->input([
            'basicSalary' => '250000.00',
            'items' => [$this->item('FUEL', '30000.00'), $this->item('PIECE_RATE', '50000.00')],
        ]));
        $this->assertSame(['280000.00', '50000.00', '330000.00'], [$r->grossSheet1, $r->grossSheet2, $r->gross]);
        $this->assertSame('20000.00', $r->amount('EPF_EE'));
        $this->assertSame('330000.00', $r->taxablePay);
        $this->assertSame('26500.00', $r->amount('APIT'));
        $this->assertSame('283500.00', $r->netPay);
    }

    public function test_late_deduction_reduces_taxable_pay_but_not_the_epf_base(): void
    {
        $r = $this->calc->calculate($this->input([
            'basicSalary' => '250000.00',
            'lateMinutesChargeable' => 60,
        ]));
        $this->assertSame('1041.67', $r->amount('LATE'));
        $this->assertSame('250000.00', $r->epfBase);
        $this->assertSame('248958.33', $r->taxablePay);
    }

    public function test_deductions_over_gross_give_zero_net_and_a_carry_forward(): void
    {
        $r = $this->calc->calculate($this->input([
            'basicSalary' => '20000.00',
            'advances' => [new PayItem('ADVANCE', '15000.00', null, 'salary_advance', 1)],
            'loanInstalments' => [new PayItem('LOAN', '8000.00', null, 'loan_instalment', 2)],
        ]));
        $this->assertSame(['20000.00', '24600.00', '0.00', '4600.00'], [$r->gross, $r->totalDeductions, $r->netPay, $r->carriedForwardOut]);
        $this->assertSame(['1600.00', '15000.00', '8000.00'], [$r->amount('EPF_EE'), $r->amount('ADVANCE'), $r->amount('LOAN')]);
    }

    public function test_carried_forward_in_is_a_deduction(): void
    {
        $r = $this->calc->calculate($this->input([
            'basicSalary' => '20000.00',
            'carriedForwardIn' => '4600.00',
        ]));
        $this->assertSame('4600.00', $r->amount('CF'));
        $this->assertSame('6200.00', $r->totalDeductions);
        $this->assertSame('13800.00', $r->netPay);
        $this->assertSame('0.00', $r->carriedForwardOut);
    }

    public function test_override_of_an_earning_flows_into_gross_and_net(): void
    {
        $r = $this->calc->calculate($this->standardExample(['overrides' => ['FUEL' => '2000.00']]));
        $this->assertSame('2000.00', $r->amount('FUEL'));
        $this->assertTrue($r->line('FUEL')->isOverride);
        $this->assertSame('76950.00', $r->gross);
        $this->assertSame('54217.00', $r->netPay);
    }

    public function test_override_of_basic_flows_into_epf(): void
    {
        $r = $this->calc->calculate($this->standardExample(['overrides' => ['BASIC' => '60000.00']]));
        $this->assertSame('4800.00', $r->amount('EPF_EE'));
        $this->assertSame('82350.00', $r->gross);
        $this->assertSame('59425.00', $r->netPay);
    }

    public function test_override_can_add_a_line_and_can_zero_a_line(): void
    {
        $add = $this->calc->calculate($this->input(['overrides' => ['SPECIAL' => '1500.00']]));
        $this->assertSame('1500.00', $add->amount('SPECIAL'));
        $this->assertTrue($add->line('SPECIAL')->isOverride);
        $this->assertSame('61500.00', $add->gross);

        $zero = $this->calc->calculate($this->standardExample(['overrides' => ['LATE' => '0.00']]));
        $this->assertSame('0.00', $zero->amount('LATE'));
        $this->assertTrue($zero->line('LATE')->isOverride);
        $this->assertSame('57342.00', $zero->netPay);
    }

    public function test_epf_override_leaves_the_employer_lines(): void
    {
        $r = $this->calc->calculate($this->input(['overrides' => ['EPF_EE' => '0.00']]));
        $this->assertSame('0.00', $r->amount('EPF_EE'));
        $this->assertSame('7200.00', $r->amount('EPF_ER'));
        $this->assertSame('60000.00', $r->netPay);
    }

    public function test_overrides_that_are_not_allowed(): void
    {
        foreach (['CF', 'ADVANCE', 'LOAN', 'EPF_ER', 'NO_SUCH'] as $code) {
            try {
                $this->calc->calculate($this->input(['overrides' => [$code => '1.00']]));
                $this->fail("Expected PayCalculationError for override {$code}");
            } catch (PayCalculationError) {
                // expected
            }
        }

        $this->expectException(PayCalculationError::class);
        $this->calc->calculate($this->input(['overrides' => ['FUEL' => '-1.00']]));
    }

    public function test_unknown_or_calculator_component_as_an_item_is_an_error(): void
    {
        try {
            $this->calc->calculate($this->input(['items' => [$this->item('NO_SUCH', '1.00')]]));
            $this->fail();
        } catch (PayCalculationError) {
            // expected
        }

        $this->expectException(PayCalculationError::class);
        $this->calc->calculate($this->input(['items' => [$this->item('BASIC', '1.00')]]));
    }

    public function test_same_input_gives_the_same_result(): void
    {
        $in = $this->standardExample();
        $a = $this->calc->calculate($in);
        $b = $this->calc->calculate($in);
        $this->assertEquals($a, $b);
    }
}
