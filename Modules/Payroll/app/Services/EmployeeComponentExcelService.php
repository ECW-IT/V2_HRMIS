<?php

namespace Modules\Payroll\Services;

use App\Exceptions\Domain\DomainException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\CoreHr\Models\Employment;
use Modules\Organisation\Models\Company;
use Modules\Payroll\Enums\CalcMethod;
use Modules\Payroll\Enums\ComponentKind;
use Modules\Payroll\Enums\OtRateMode;
use Modules\Payroll\Exceptions\InvalidCompensation;
use Modules\Payroll\Models\EmployeeComponent;
use Modules\Payroll\Models\PayComponent;

final class EmployeeComponentExcelService
{
    /** @var list<CalcMethod> */
    private const ATTACHABLE = [
        CalcMethod::FIXED,
        CalcMethod::PROPORTIONAL,
        CalcMethod::QUANTITY_RATE,
        CalcMethod::NIGHT_UNITS,
    ];

    private const OT_RATE = '__OT_RATE__';

    private const EMP_NO = '__EMP_NO__';

    /** Soft-skip codes → warning message. */
    private const SOFT_SKIP = [
        'ATT_ALLOW' => 'Attendance allowance is set by tier, not amount (skipped).',
        'MEALS' => 'Meals deduction is a pay-period entry, not a standing component (skipped).',
        'SALES_BONUS' => 'Sales bonus is a pay-period entry, not a standing component (skipped).',
        'OT_HOURS' => 'Over time hours are period attendance data, not standing pay (skipped).',
    ];

    /** Header slug / alias → component code or special marker. */
    private const ALIASES = [
        'emp_no' => self::EMP_NO,
        'empno' => self::EMP_NO,
        'employee_no' => self::EMP_NO,
        'employee_number' => self::EMP_NO,
        'employee_no_' => self::EMP_NO,
        'accommodation_allowance' => 'ACCOM',
        'accom' => 'ACCOM',
        'attendance_allowance' => 'ATT_ALLOW',
        'att_allow' => 'ATT_ALLOW',
        'meals_deduction' => 'MEALS',
        'meals' => 'MEALS',
        'ot_rate' => self::OT_RATE,
        'permance_allowance' => 'PERF',
        'performance_allowance' => 'PERF',
        'perf' => 'PERF',
        'travel_allowance' => 'TRAVEL',
        'travel' => 'TRAVEL',
        'fuel_allowance' => 'FUEL',
        'fuel' => 'FUEL',
        'hostel_fees' => 'HOSTEL',
        'hostel' => 'HOSTEL',
        'incentive_i' => 'INCENTIVE_1',
        'incentive_1' => 'INCENTIVE_1',
        'incentive_ii' => 'INCENTIVE_2',
        'incentive_2' => 'INCENTIVE_2',
        'mobile_allowance' => 'MOBILE',
        'mobile' => 'MOBILE',
        'over_time_hours' => 'OT_HOURS',
        'overtime_hours' => 'OT_HOURS',
        'ot_hours' => 'OT_HOURS',
        'sales_bonus' => 'SALES_BONUS',
        'special_allowance' => 'SPECIAL',
        'special' => 'SPECIAL',
        'temporary_allowance' => 'TEMP_ALLOW',
        'temp_allow' => 'TEMP_ALLOW',
        'temp_allowance' => 'TEMP_ALLOW',
        'vehicle_allowance' => 'VEHICLE',
        'vehicle' => 'VEHICLE',
    ];

    public function __construct(private readonly CompensationService $compensation) {}

    /**
     * @param  list<array{row: int, data: array<string, string>}>  $rows
     * @return array{success_count: int, error_count: int, errors: list<string>, warnings: list<string>, updated: list<string>}
     */
    public function process(Company $company, array $rows, string $effectiveFrom): array
    {
        $effectiveFrom = CarbonImmutable::parse($effectiveFrom, config('app.timezone'))->toDateString();
        $this->ensureTemporaryAllowanceComponent();
        $componentsByKey = $this->componentLookup();

        $successCount = 0;
        $errorCount = 0;
        $errors = [];
        $warnings = [];
        $updated = [];

        foreach ($rows as $entry) {
            $rowNumber = $entry['row'];
            $data = $entry['data'];

            try {
                $employeeNo = $this->employeeNo($data);
                if ($employeeNo === '') {
                    throw new InvalidCompensation('Employee number is required.');
                }

                $employment = Employment::query()
                    ->where('company_id', $company->id)
                    ->where('employee_no', $employeeNo)
                    ->first();

                if ($employment === null) {
                    throw new InvalidCompensation('Unknown employee number.');
                }

                $parsed = $this->parseRow($data, $componentsByKey);
                foreach ($parsed['warnings'] as $warning) {
                    $warnings[] = "Row {$rowNumber}: {$warning}";
                }

                if ($parsed['components'] === [] && $parsed['ot_rate'] === null) {
                    continue;
                }

                DB::transaction(function () use ($employment, $parsed, $effectiveFrom) {
                    if ($parsed['ot_rate'] !== null) {
                        $this->applyOtRate($employment, $parsed['ot_rate'], $effectiveFrom);
                    }

                    foreach ($parsed['components'] as $code => $amount) {
                        $this->applyAmount($employment, $code, $amount, $effectiveFrom);
                    }
                });

                $successCount++;
                $updated[] = $employeeNo;
            } catch (DomainException|\InvalidArgumentException $e) {
                $errorCount++;
                $errors[] = "Row {$rowNumber}: ".$e->getMessage();
            } catch (\Throwable $e) {
                $errorCount++;
                $errors[] = "Row {$rowNumber}: ".$e->getMessage();
            }
        }

        return [
            'success_count' => $successCount,
            'error_count' => $errorCount,
            'errors' => $errors,
            'warnings' => $warnings,
            'updated' => $updated,
        ];
    }

    /**
     * @return array<string, PayComponent> keyed by normalized slug / code
     */
    private function componentLookup(): array
    {
        $map = [];

        $components = PayComponent::query()
            ->where('is_active', true)
            ->orderBy('display_order')
            ->get();

        foreach ($components as $component) {
            $map[strtolower($component->code)] = $component;
            $map[$this->slug($component->name)] = $component;
            $map[$this->slug($component->code)] = $component;
        }

        return $map;
    }

    /**
     * @param  array<string, string>  $data
     * @param  array<string, PayComponent>  $componentsByKey
     * @return array{components: array<string, string>, ot_rate: ?string, warnings: list<string>}
     */
    private function parseRow(array $data, array $componentsByKey): array
    {
        $components = [];
        $otRate = null;
        $warnings = [];

        foreach ($data as $header => $rawAmount) {
            $slug = $this->slug((string) $header);
            if ($slug === '' || $this->isEmpNoKey($slug)) {
                continue;
            }

            $amount = str_replace([',', ' '], '', trim($rawAmount));
            if ($amount === '' || $this->isBlankAmount($amount)) {
                continue;
            }

            $code = self::ALIASES[$slug] ?? null;
            if ($code === self::EMP_NO) {
                continue;
            }

            if ($code === self::OT_RATE) {
                if (! is_numeric($amount) || bccomp($amount, '0', 2) <= 0) {
                    // Soft: blank/zero OT rate means leave compensation unchanged.
                    continue;
                }
                $otRate = number_format((float) $amount, 2, '.', '');

                continue;
            }

            if ($code !== null && array_key_exists($code, self::SOFT_SKIP)) {
                $warnings[] = self::SOFT_SKIP[$code];

                continue;
            }

            $component = null;
            if ($code !== null) {
                $component = $componentsByKey[strtolower($code)] ?? null;
            } else {
                $component = $componentsByKey[$slug] ?? null;
            }

            if ($component === null) {
                throw new InvalidCompensation('Unknown component column: '.$header.'.');
            }

            $method = $component->method instanceof CalcMethod
                ? $component->method
                : CalcMethod::from((string) $component->method);

            if (array_key_exists($component->code, self::SOFT_SKIP)
                || $method === CalcMethod::ATTENDANCE_TIER
                || $method === CalcMethod::ENTRY) {
                $warnings[] = self::SOFT_SKIP[$component->code]
                    ?? ($component->name.' is not a standing amount column (skipped).');

                continue;
            }

            if (! in_array($method, self::ATTACHABLE, true)) {
                throw new InvalidCompensation($component->name.' is not attachable by amount.');
            }

            // Soft: zero/placeholder amounts mean "no component" — skip, do not fail the row.
            if (! is_numeric($amount) || bccomp($amount, '0', 2) <= 0) {
                continue;
            }

            $components[$component->code] = number_format((float) $amount, 2, '.', '');
        }

        return [
            'components' => $components,
            'ot_rate' => $otRate,
            'warnings' => $warnings,
        ];
    }

    private function applyOtRate(Employment $employment, string $otRate, string $effectiveFrom): void
    {
        // Compensation changes must start on the 1st; snap Excel "effective from" to that month.
        $otFrom = $this->otRateEffectiveFrom($employment, $effectiveFrom);

        $current = $this->compensation->on($employment, $otFrom);
        if ($current === null) {
            $current = $this->compensation->on($employment, $effectiveFrom);
        }
        if ($current === null) {
            throw new InvalidCompensation('No compensation exists to set OT rate.');
        }

        $mode = $current->ot_rate_mode instanceof OtRateMode
            ? $current->ot_rate_mode
            : OtRateMode::from((string) $current->ot_rate_mode);

        $existingRate = $current->ot_rate !== null
            ? number_format((float) $current->ot_rate, 2, '.', '')
            : null;

        if ($mode === OtRateMode::FIXED && $existingRate !== null && bccomp($existingRate, $otRate, 2) === 0) {
            return;
        }

        $this->compensation->set($employment, $otFrom, [
            'basic_salary' => (string) $current->basic_salary,
            'ot_rate_mode' => OtRateMode::FIXED,
            'ot_rate' => $otRate,
            'attendance_tier_code' => $current->attendance_tier_code,
            'change_reason' => 'Excel payroll upload',
        ]);
    }

    private function otRateEffectiveFrom(Employment $employment, string $effectiveFrom): string
    {
        $tz = config('app.timezone');
        $otFrom = CarbonImmutable::parse($effectiveFrom, $tz)->startOfMonth();
        $hire = CarbonImmutable::parse($employment->hire_date, $tz)->startOfDay();

        if ($otFrom->lt($hire)) {
            $otFrom = $hire->day === 1
                ? $hire
                : $hire->startOfMonth()->addMonth();
        }

        return $otFrom->toDateString();
    }

    private function ensureTemporaryAllowanceComponent(): void
    {
        if (PayComponent::query()->where('code', 'TEMP_ALLOW')->exists()) {
            return;
        }

        PayComponent::query()->create([
            'code' => 'TEMP_ALLOW',
            'name' => 'Temporary allowance',
            'kind' => ComponentKind::EARNING,
            'method' => CalcMethod::FIXED,
            'sheet' => 1,
            'epf_liable' => false,
            'taxable' => true,
            'fund' => null,
            'night_base_hours' => null,
            'is_system' => false,
            'is_active' => true,
            'display_order' => 185,
        ]);
    }

    private function applyAmount(Employment $employment, string $code, string $amount, string $effectiveFrom): void
    {
        $hire = CarbonImmutable::parse($employment->hire_date, config('app.timezone'))->toDateString();
        $from = $effectiveFrom < $hire ? $hire : $effectiveFrom;

        $overlapping = $this->overlappingComponents($employment, $code, $from);

        if ($overlapping->isEmpty()) {
            $this->compensation->addComponent($employment, $code, $amount, $from);

            return;
        }

        // Soft: same amount already covering this period → skip (re-upload safe).
        $sameAmount = $overlapping->first(
            fn (EmployeeComponent $row) => bccomp(number_format((float) $row->amount, 2, '.', ''), $amount, 2) === 0
        );
        if ($sameAmount !== null) {
            return;
        }

        /** @var EmployeeComponent $primary */
        $primary = $overlapping->first(
            function (EmployeeComponent $row) use ($from) {
                $rowFrom = CarbonImmutable::parse($row->effective_from)->toDateString();

                return $rowFrom <= $from;
            }
        ) ?? $overlapping->first();

        $primaryFrom = CarbonImmutable::parse($primary->effective_from)->toDateString();

        if ($primaryFrom >= $from) {
            $primary->update([
                'amount' => $amount,
                'effective_from' => $from,
            ]);

            return;
        }

        $endOn = CarbonImmutable::parse($from)->subDay()->toDateString();
        if ($endOn < $primaryFrom) {
            $primary->update(['amount' => $amount]);

            return;
        }

        $this->compensation->endComponent($primary, $endOn);

        try {
            $this->compensation->addComponent($employment, $code, $amount, $from);
        } catch (InvalidCompensation $e) {
            if (! str_contains($e->getMessage(), 'already set')) {
                throw $e;
            }

            $still = $this->overlappingComponents($employment, $code, $from);
            if ($still->isEmpty()) {
                throw $e;
            }

            $still->first()->update(['amount' => $amount]);
        }
    }

    /** @return Collection<int, EmployeeComponent> */
    private function overlappingComponents(Employment $employment, string $code, string $from)
    {
        return EmployeeComponent::query()
            ->with('payComponent')
            ->where('employment_id', $employment->id)
            ->whereHas('payComponent', fn ($q) => $q->where('code', $code))
            ->whereDate('effective_from', '<=', '9999-12-31')
            ->where(function ($q) use ($from) {
                $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $from);
            })
            ->orderBy('effective_from')
            ->get();
    }

    /** @param  array<string, string>  $data */
    private function employeeNo(array $data): string
    {
        foreach ($data as $header => $value) {
            $slug = $this->slug((string) $header);
            if ($this->isEmpNoKey($slug)) {
                return trim($value);
            }
        }

        return trim($data['emp_no'] ?? $data['employee_no'] ?? '');
    }

    private function isEmpNoKey(string $slug): bool
    {
        return ($slug !== '') && ((self::ALIASES[$slug] ?? null) === self::EMP_NO);
    }

    private function slug(string $value): string
    {
        return Str::slug(trim($value), '_');
    }

    private function isBlankAmount(string $value): bool
    {
        $trimmed = mb_strtolower(trim($value));

        return in_array($trimmed, ['-', '—', '–', '.', 'n/a', 'na', 'nil', 'null', '0', '0.0', '0.00'], true);
    }
}
