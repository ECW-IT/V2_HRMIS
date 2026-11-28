<?php

namespace Modules\Payroll\Run;

use Carbon\CarbonImmutable;
use Modules\Attendance\Models\PeriodTimeSummary;
use Modules\CoreHr\Models\Employment;
use Modules\CoreHr\Services\BankAccountService;
use Modules\Payroll\Calculator\ComponentSpec;
use Modules\Payroll\Calculator\PayInput;
use Modules\Payroll\Calculator\PayItem;
use Modules\Payroll\Calculator\TierSpec;
use Modules\Payroll\Enums\CalcMethod;
use Modules\Payroll\Exceptions\PayCalculationError;
use Modules\Payroll\Models\PayRun;
use Modules\Payroll\Models\PayRunOverride;
use Modules\Payroll\Rules\PayRules;
use Modules\Payroll\Services\AdvanceService;
use Modules\Payroll\Services\ArrearsService;
use Modules\Payroll\Services\CompensationService;
use Modules\Payroll\Services\LoanService;
use Modules\Payroll\Services\PayEntryService;
use Modules\Payroll\Services\PayRuleService;

final class InputBuilder
{
    public function __construct(
        private readonly CompensationService $compensation,
        private readonly PayRuleService $payRules,
        private readonly PayEntryService $payEntries,
        private readonly ArrearsService $arrears,
        private readonly AdvanceService $advances,
        private readonly LoanService $loans,
        private readonly BankAccountService $banks,
    ) {}

    /**
     * @param  array<string, ComponentSpec>  $components
     * @return array{input: PayInput, snapshot: array<string, mixed>}
     */
    public function build(PeriodTimeSummary $s, PayRun $run, PayRules $rules, array $components): array
    {
        $employment = Employment::query()->with('person')->findOrFail($s->employment_id);
        $period = $run->payPeriod;
        $lookup = $this->lookupDate($employment, $period->end_date->toDateString());

        $comp = $this->compensation->on($employment, $lookup);
        if ($comp === null) {
            throw new PayCalculationError('No compensation on '.$lookup.'.');
        }

        $basic = $this->money($comp->basic_salary);
        $otRate = $this->compensation->otRate($comp, $rules);
        $categoryId = $s->employee_category_id !== null ? (int) $s->employee_category_id : 0;
        $extraDayMethod = $categoryId === 0 ? null : $this->payRules->extraDayMethod($categoryId, $lookup);

        $tier = null;
        $tierCode = $comp->attendance_tier_code;
        if ($tierCode !== null && $tierCode !== '') {
            $tierRow = $this->payRules->tier((string) $tierCode, $lookup);
            if ($tierRow !== null) {
                $tier = new TierSpec($this->money($tierRow->base_amount), $this->money($tierRow->per_day_rate));
            }
        }

        $items = $this->buildItems($employment, $period, $lookup);
        $overrides = $this->activeOverrides($run, (int) $employment->id);
        $advanceItems = $this->advanceItems((int) $employment->id, $period);
        $loanItems = $this->loanItems((int) $employment->id, $period);
        $cfIn = $this->arrears->balance((int) $employment->id);

        $assignment = $employment->assignmentOn($lookup);
        $bank = $this->banks->on($employment, $lookup);

        $expected = $this->qty($s->expected_days);
        $credited = $this->qty($s->credited_days);
        $extra = $this->qty($s->extra_days);
        $nopay = $this->qty($s->nopay_days);
        $late = (int) $s->late_minutes_chargeable;
        $otMinutes = (int) $s->ot_normal_minutes;

        $input = new PayInput(
            basicSalary: $basic,
            expectedDays: $expected,
            creditedDays: $credited,
            extraDays: $extra,
            lateMinutesChargeable: $late,
            otMinutes: $otMinutes,
            epfLiable: (bool) $employment->epf_liable,
            otRate: $otRate,
            extraDayMethod: $extraDayMethod,
            tier: $tier,
            rules: $rules,
            components: $components,
            items: $items,
            carriedForwardIn: $cfIn,
            advances: $advanceItems,
            loanInstalments: $loanItems,
            overrides: $overrides,
        );

        $name = $employment->person?->name_with_initials ?? trim(($employment->person?->first_name ?? '').' '.($employment->person?->last_name ?? ''));

        $snapshot = [
            'employment_id' => $employment->id,
            'employee_no' => (string) $employment->employee_no,
            'name' => mb_substr($name, 0, 150),
            'employee_category_id' => $s->employee_category_id !== null ? (int) $s->employee_category_id : null,
            'org_unit_id' => $assignment?->org_unit_id,
            'designation_id' => $assignment?->designation_id,
            'employment_type' => $employment->type instanceof \BackedEnum ? $employment->type->value : (string) $employment->type,
            'epf_liable' => (bool) $employment->epf_liable,
            'epf_no' => $employment->epf_no,
            'basic_salary' => $basic,
            'ot_rate' => $otRate,
            'extra_day_method' => $extraDayMethod?->value,
            'attendance_tier_code' => $tierCode !== null && $tierCode !== '' ? (string) $tierCode : null,
            'expected_days' => $expected,
            'credited_days' => $credited,
            'extra_days' => $extra,
            'nopay_days' => $nopay,
            'late_minutes_chargeable' => $late,
            'ot_minutes' => $otMinutes,
            'carried_forward_in' => $cfIn,
            'is_cash' => (bool) ($bank?->is_cash ?? false),
            'bank_id' => $bank?->bank_id,
            'bank_branch_id' => $bank?->bank_branch_id,
            'account_no' => $bank?->account_no,
            'account_name' => $bank?->account_name,
        ];

        return ['input' => $input, 'snapshot' => $snapshot];
    }

    private function lookupDate(Employment $e, string $periodEnd): string
    {
        if ($e->exit_date === null) {
            return $periodEnd;
        }

        $exit = CarbonImmutable::parse($e->exit_date)->toDateString();

        return $exit < $periodEnd ? $exit : $periodEnd;
    }

    /** @return list<PayItem> */
    private function buildItems(Employment $employment, $period, string $lookup): array
    {
        $items = [];
        $entries = $this->payEntries->for((int) $employment->id, $period);

        foreach ($this->compensation->componentsOn($employment, $lookup) as $row) {
            $pc = $row->payComponent;
            if ($pc === null) {
                continue;
            }
            $method = $pc->method instanceof CalcMethod ? $pc->method : CalcMethod::from((string) $pc->method);
            $code = (string) $pc->code;
            $amount = $this->money($row->amount);

            if ($method === CalcMethod::FIXED || $method === CalcMethod::PROPORTIONAL) {
                $items[] = new PayItem($code, $amount, null, 'employee_component', (int) $row->id);
            } elseif ($method === CalcMethod::QUANTITY_RATE || $method === CalcMethod::NIGHT_UNITS) {
                $qty = $entries
                    ->where('pay_component_id', $pc->id)
                    ->sum(fn ($e) => (float) ($e->quantity ?? 0));
                if ($qty > 0) {
                    $items[] = new PayItem($code, $amount, number_format($qty, 2, '.', ''), 'employee_component', (int) $row->id);
                }
            }
        }

        foreach ($entries as $entry) {
            $pc = $entry->payComponent;
            if ($pc === null) {
                $entry->load('payComponent');
                $pc = $entry->payComponent;
            }
            if ($pc === null) {
                continue;
            }
            $method = $pc->method instanceof CalcMethod ? $pc->method : CalcMethod::from((string) $pc->method);
            if ($method !== CalcMethod::ENTRY) {
                continue;
            }
            $items[] = new PayItem(
                (string) $pc->code,
                $this->money($entry->amount),
                null,
                'pay_entry',
                (int) $entry->id,
            );
        }

        return $items;
    }

    /** @return array<string, string> */
    private function activeOverrides(PayRun $run, int $employmentId): array
    {
        $rows = PayRunOverride::query()
            ->with('payComponent')
            ->where('pay_run_id', $run->id)
            ->where('employment_id', $employmentId)
            ->whereNull('cancelled_at')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $code = $row->payComponent?->code;
            if ($code !== null) {
                $out[(string) $code] = $this->money($row->amount);
            }
        }

        return $out;
    }

    /** @return list<PayItem> */
    private function advanceItems(int $employmentId, $period): array
    {
        $items = [];
        foreach ($this->advances->dueFor($employmentId, $period) as $a) {
            $items[] = new PayItem('ADVANCE', $this->money($a->amount), null, 'salary_advance', (int) $a->id);
        }

        return $items;
    }

    /** @return list<PayItem> */
    private function loanItems(int $employmentId, $period): array
    {
        $items = [];
        foreach ($this->loans->dueFor($employmentId, $period) as $i) {
            $items[] = new PayItem('LOAN', $this->money($i->amount), null, 'loan_instalment', (int) $i->id);
        }

        return $items;
    }

    private function money(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    private function qty(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
