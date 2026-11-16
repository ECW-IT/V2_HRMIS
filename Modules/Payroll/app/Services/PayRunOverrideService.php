<?php

namespace Modules\Payroll\Services;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Modules\Attendance\Services\SummaryService;
use Modules\CoreHr\Models\Employment;
use Modules\Payroll\Calculator\ComponentSpec;
use Modules\Payroll\Calculator\PayCalculator;
use Modules\Payroll\Calculator\PayInput;
use Modules\Payroll\Enums\PayRunStatus;
use Modules\Payroll\Exceptions\InvalidPayRunAction;
use Modules\Payroll\Exceptions\PayCalculationError;
use Modules\Payroll\Models\PayComponent;
use Modules\Payroll\Models\PayRun;
use Modules\Payroll\Models\PayRunOverride;
use Modules\Payroll\Run\InputBuilder;

final class PayRunOverrideService
{
    public function __construct(
        private readonly PayRunService $runs,
        private readonly InputBuilder $builder,
        private readonly PayComponentService $components,
        private readonly PayRuleService $payRules,
    ) {}

    public function set(PayRun $run, Employment $e, string $componentCode, string $amount, string $reason, User $by): PayRunOverride
    {
        $this->authorize($by, 'payrun.override', (int) $run->company_id);
        $this->assertOpen($run);
        $this->refuseSelf($by, $e);

        $reason = trim($reason);
        if (mb_strlen($reason) < 10) {
            throw new InvalidPayRunAction('A reason of at least 10 characters is required.');
        }

        if (! $run->employees()->where('employment_id', $e->id)->exists()) {
            throw new InvalidPayRunAction('The employee is not in this pay run.');
        }

        $component = PayComponent::query()->where('code', strtoupper(trim($componentCode)))->first();
        if ($component === null) {
            throw new InvalidPayRunAction('Unknown pay component.');
        }

        $amount = $this->money($amount);
        if (bccomp($amount, '0.00', 2) < 0) {
            throw new InvalidPayRunAction('Override amount cannot be negative.');
        }

        $this->assertOverrideAllowed($run, $e, (string) $component->code, $amount);

        return DB::transaction(function () use ($run, $e, $component, $amount, $reason, $by) {
            PayRunOverride::query()
                ->where('pay_run_id', $run->id)
                ->where('employment_id', $e->id)
                ->where('pay_component_id', $component->id)
                ->whereNull('cancelled_at')
                ->update(['cancelled_at' => now()]);

            $override = PayRunOverride::query()->create([
                'pay_run_id' => $run->id,
                'employment_id' => $e->id,
                'pay_component_id' => $component->id,
                'amount' => $amount,
                'reason' => $reason,
                'set_by' => $by->id,
                'cancelled_at' => null,
            ]);

            $this->runs->recalculateEmployee($run->fresh(), (int) $e->id, $by);

            return $override->fresh();
        });
    }

    public function cancel(PayRunOverride $o, User $by): void
    {
        $run = $o->payRun;
        $this->authorize($by, 'payrun.override', (int) $run->company_id);
        $this->assertOpen($run);

        if ($o->cancelled_at !== null) {
            throw new InvalidPayRunAction('The override is already cancelled.');
        }

        DB::transaction(function () use ($o, $run, $by) {
            $o->update(['cancelled_at' => now()]);
            $this->runs->recalculateEmployee($run->fresh(), (int) $o->employment_id, $by);
        });
    }

    private function assertOverrideAllowed(PayRun $run, Employment $e, string $code, string $amount): void
    {
        $period = $run->payPeriod;
        $summary = app(SummaryService::class)->final($period)
            ->firstWhere('employment_id', $e->id);

        if ($summary === null) {
            throw new InvalidPayRunAction('The employee is not in this pay run.');
        }

        try {
            $rules = $this->payRules->rulesFor($run->company, $period->end_date);
            $components = $this->componentSpecs();
            $built = $this->builder->build($summary, $run, $rules, $components);
            $input = $built['input'];
            $overrides = $input->overrides;
            $overrides[$code] = $amount;
            $trial = new PayInput(
                basicSalary: $input->basicSalary,
                expectedDays: $input->expectedDays,
                creditedDays: $input->creditedDays,
                extraDays: $input->extraDays,
                lateMinutesChargeable: $input->lateMinutesChargeable,
                otMinutes: $input->otMinutes,
                epfLiable: $input->epfLiable,
                otRate: $input->otRate,
                extraDayMethod: $input->extraDayMethod,
                tier: $input->tier,
                rules: $input->rules,
                components: $input->components,
                items: $input->items,
                carriedForwardIn: $input->carriedForwardIn,
                advances: $input->advances,
                loanInstalments: $input->loanInstalments,
                overrides: $overrides,
            );
            app(PayCalculator::class)->calculate($trial);
        } catch (PayCalculationError $ex) {
            throw new InvalidPayRunAction($ex->getMessage(), 0, $ex);
        }
    }

    /** @return array<string, ComponentSpec> */
    private function componentSpecs(): array
    {
        $out = [];
        foreach ($this->components->catalogue() as $c) {
            $out[$c->code] = new ComponentSpec(
                $c->code,
                $c->kind,
                $c->method,
                (int) $c->sheet,
                (bool) $c->epf_liable,
                (bool) $c->taxable,
                (int) $c->display_order,
                $c->night_base_hours !== null ? (int) $c->night_base_hours : null,
            );
        }

        return $out;
    }

    private function assertOpen(PayRun $run): void
    {
        if (! in_array($run->status, [PayRunStatus::DRAFT, PayRunStatus::CALCULATED], true)) {
            throw new InvalidPayRunAction('Overrides are only allowed on a draft or calculated run.');
        }
    }

    private function refuseSelf(User $by, Employment $e): void
    {
        if ($by->person_id !== null && (int) $by->person_id === (int) $e->person_id) {
            throw new AuthorizationException('You cannot override your own pay.');
        }
    }

    private function authorize(User $by, string $permission, int $companyId): void
    {
        if (! $by->can($permission) || ! $this->canAccessCompany($by, $companyId)) {
            throw new AuthorizationException('This action is unauthorized.');
        }
    }

    private function canAccessCompany(User $by, int $companyId): bool
    {
        if ($by->all_companies) {
            return true;
        }

        return $by->companies()->where('companies.id', $companyId)->exists();
    }

    private function money(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
