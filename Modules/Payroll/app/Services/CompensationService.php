<?php

namespace Modules\Payroll\Services;

use App\Support\Effective\EffectiveDating;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Modules\CoreHr\Enums\EmploymentType;
use Modules\CoreHr\Models\Employment;
use Modules\Payroll\Enums\CalcMethod;
use Modules\Payroll\Enums\OtRateMode;
use Modules\Payroll\Exceptions\InvalidCompensation;
use Modules\Payroll\Models\EmployeeCompensation;
use Modules\Payroll\Models\EmployeeComponent;
use Modules\Payroll\Models\PayComponent;
use Modules\Payroll\Rules\PayRules;

final class CompensationService
{
    /** @var list<CalcMethod> */
    private const ATTACHABLE = [
        CalcMethod::FIXED,
        CalcMethod::PROPORTIONAL,
        CalcMethod::QUANTITY_RATE,
        CalcMethod::NIGHT_UNITS,
    ];

    public function __construct(private readonly PayRuleService $payRules) {}

    /** $data keys: basic_salary, ot_rate_mode, ot_rate?, attendance_tier_code?, change_reason?. */
    public function set(Employment $e, string $from, array $data, ?int $approvalRequestId = null): EmployeeCompensation
    {
        $from = $this->dateString($from);
        $hire = $this->dateString($e->hire_date);

        if ($from < $hire) {
            throw new InvalidCompensation('Compensation effective date is before the hire date.');
        }

        $this->assertPeriodNotLocked($from);

        $existing = EmployeeCompensation::query()->where('employment_id', $e->id)->exists();
        if ($existing && ! $this->isFirstOfMonth($from)) {
            throw new InvalidCompensation('A compensation change must start on the first day of a month.');
        }

        if (! $existing && $from !== $hire && ! $this->isFirstOfMonth($from)) {
            throw new InvalidCompensation('A compensation change must start on the first day of a month.');
        }

        $basic = $this->money($data['basic_salary'] ?? null);
        $type = $e->type instanceof EmploymentType ? $e->type : EmploymentType::from((string) $e->type);

        if (bccomp($basic, '0.00', 2) < 0) {
            throw new InvalidCompensation('Basic salary cannot be negative.');
        }

        if ($type === EmploymentType::INTERN) {
            if (bccomp($basic, '0.00', 2) !== 0) {
                throw new InvalidCompensation('An intern must have a basic salary of zero.');
            }
        } elseif (bccomp($basic, '0.00', 2) <= 0) {
            throw new InvalidCompensation('Basic salary must be greater than zero.');
        }

        $mode = $data['ot_rate_mode'] instanceof OtRateMode
            ? $data['ot_rate_mode']
            : OtRateMode::from((string) ($data['ot_rate_mode'] ?? ''));

        $otRate = null;
        if ($mode === OtRateMode::FIXED) {
            $otRate = $this->money($data['ot_rate'] ?? null);
            if (bccomp($otRate, '0.00', 2) <= 0) {
                throw new InvalidCompensation('A fixed OT rate must be greater than zero.');
            }
        }

        $tierCode = $data['attendance_tier_code'] ?? null;
        if ($tierCode !== null && $tierCode !== '') {
            $tierCode = (string) $tierCode;
            if ($this->payRules->tier($tierCode, $from) === null) {
                throw new InvalidCompensation('Unknown attendance tier.');
            }
        } else {
            $tierCode = null;
        }

        $payload = [
            'company_id' => $e->company_id,
            'employment_id' => $e->id,
            'effective_from' => $from,
            'basic_salary' => $basic,
            'ot_rate_mode' => $mode,
            'ot_rate' => $otRate,
            'attendance_tier_code' => $tierCode,
            'change_reason' => $data['change_reason'] ?? null,
            'approval_request_id' => $approvalRequestId,
        ];

        /** @var EmployeeCompensation $saved */
        $saved = EffectiveDating::insert(new EmployeeCompensation($payload));

        return $saved->fresh();
    }

    public function on(Employment $e, \DateTimeInterface|string $date): ?EmployeeCompensation
    {
        return EmployeeCompensation::query()
            ->where('employment_id', $e->id)
            ->effectiveOn($date)
            ->first();
    }

    /** OT rate in force: FIXED -> ot_rate; CALCULATED -> round(basic × ot_multiplier ÷ hours_per_month, 2), half-up. */
    public function otRate(EmployeeCompensation $c, PayRules $rules): string
    {
        $mode = $c->ot_rate_mode instanceof OtRateMode
            ? $c->ot_rate_mode
            : OtRateMode::from((string) $c->ot_rate_mode);

        if ($mode === OtRateMode::FIXED) {
            return $this->money($c->ot_rate);
        }

        $product = bcmul((string) $c->basic_salary, $rules->otMultiplier, 6);
        $raw = bcdiv($product, (string) $rules->hoursPerMonth, 6);

        return $this->money(round((float) $raw, 2, PHP_ROUND_HALF_UP));
    }

    public function addComponent(
        Employment $e,
        string $componentCode,
        string $amount,
        string $from,
        ?string $to = null,
        ?string $note = null,
    ): EmployeeComponent {
        $from = $this->dateString($from);
        $to = $to !== null && $to !== '' ? $this->dateString($to) : null;
        $hire = $this->dateString($e->hire_date);

        if ($from < $hire) {
            throw new InvalidCompensation('Component effective date is before the hire date.');
        }

        if ($to !== null && $to < $from) {
            throw new InvalidCompensation('Component end date cannot be before the start date.');
        }

        $this->assertPeriodNotLocked($from);

        $component = PayComponent::query()->where('code', $componentCode)->first();
        if ($component === null || ! $component->is_active) {
            throw new InvalidCompensation('Component is not attachable.');
        }

        $method = $component->method instanceof CalcMethod
            ? $component->method
            : CalcMethod::from((string) $component->method);

        if (! in_array($method, self::ATTACHABLE, true)) {
            throw new InvalidCompensation('Component is not attachable.');
        }

        $amount = $this->money($amount);
        if (bccomp($amount, '0.00', 2) <= 0) {
            throw new InvalidCompensation('Component amount must be greater than zero.');
        }

        $overlap = EmployeeComponent::query()
            ->where('employment_id', $e->id)
            ->where('pay_component_id', $component->id)
            ->where(function ($q) use ($from, $to) {
                $q->where(function ($inner) use ($from, $to) {
                    $inner->whereDate('effective_from', '<=', $to ?? '9999-12-31')
                        ->where(function ($open) use ($from) {
                            $open->whereNull('effective_to')
                                ->orWhereDate('effective_to', '>=', $from);
                        });
                });
            })
            ->exists();

        if ($overlap) {
            throw new InvalidCompensation($component->name.' is already set for those dates.');
        }

        return EmployeeComponent::query()->create([
            'company_id' => $e->company_id,
            'employment_id' => $e->id,
            'pay_component_id' => $component->id,
            'amount' => $amount,
            'effective_from' => $from,
            'effective_to' => $to,
            'note' => $note,
        ]);
    }

    public function endComponent(EmployeeComponent $c, string $on): EmployeeComponent
    {
        $on = $this->dateString($on);
        $from = $this->dateString($c->effective_from);

        if ($on < $from) {
            throw new InvalidCompensation('Component end date cannot be before the start date.');
        }

        $this->assertPeriodNotLocked($on);

        $c->update(['effective_to' => $on]);

        return $c->fresh();
    }

    /** Components in force on the date, with their pay component, in display order. */
    public function componentsOn(Employment $e, \DateTimeInterface|string $date): Collection
    {
        $day = $this->dateString($date);

        return EmployeeComponent::query()
            ->with('payComponent')
            ->where('employment_id', $e->id)
            ->whereDate('effective_from', '<=', $day)
            ->where(function ($q) use ($day) {
                $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $day);
            })
            ->get()
            ->sortBy(fn (EmployeeComponent $row) => $row->payComponent?->display_order ?? PHP_INT_MAX)
            ->values();
    }

    /** Stub for Task 33 — locked pay-run check. */
    private function assertPeriodNotLocked(string $on): void
    {
        // Task 33 will enforce locked pay runs.
    }

    private function isFirstOfMonth(string $date): bool
    {
        return CarbonImmutable::parse($date)->day === 1;
    }

    private function money(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '0.00';
        }

        return number_format((float) $value, 2, '.', '');
    }

    private function dateString(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return (string) $value;
    }
}
