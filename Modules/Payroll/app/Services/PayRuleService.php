<?php

namespace Modules\Payroll\Services;

use App\Support\Effective\EffectiveDating;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Modules\Organisation\Models\Company;
use Modules\Organisation\Models\Grade;
use Modules\Payroll\Enums\ExtraDayMethod;
use Modules\Payroll\Enums\PayRunStatus;
use Modules\Payroll\Exceptions\InvalidPayRule;
use Modules\Payroll\Models\AttendanceAllowanceTier;
use Modules\Payroll\Models\CompanyPaySetting;
use Modules\Payroll\Models\GradePayRule;
use Modules\Payroll\Models\PayRun;
use Modules\Payroll\Models\StatutoryRate;
use Modules\Payroll\Models\TaxSlab;
use Modules\Payroll\Rules\PayRules;

final class PayRuleService
{
    public function rulesFor(Company $c, \DateTimeInterface|string $on): PayRules
    {
        $day = $this->asDate($on);
        $missing = [];

        $epfEe = $this->statutoryOn('EPF_EE', $day);
        if ($epfEe === null) {
            $missing[] = 'No EPF_EE rate';
        }

        $epfEr = $this->statutoryOn('EPF_ER', $day);
        if ($epfEr === null) {
            $missing[] = 'No EPF_ER rate';
        }

        $etf = $this->statutoryOn('ETF', $day);
        if ($etf === null) {
            $missing[] = 'No ETF rate';
        }

        $slabs = TaxSlab::query()
            ->whereDate('effective_from', '<=', $day)
            ->where(function ($q) use ($day) {
                $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $day);
            })
            ->orderBy('sequence')
            ->get();

        if ($slabs->isEmpty()) {
            $missing[] = 'No tax table';
        }

        $settings = CompanyPaySetting::query()
            ->where('company_id', $c->id)
            ->effectiveOn($day)
            ->first();

        if ($settings === null) {
            $missing[] = 'No pay settings for '.$c->name;
        }

        if ($missing !== []) {
            throw new InvalidPayRule(implode('; ', $missing).'.');
        }

        return new PayRules(
            epfEmployeePercent: $this->money($epfEe->percent),
            epfEmployerPercent: $this->money($epfEr->percent),
            etfPercent: $this->money($etf->percent),
            taxSlabs: $slabs->map(fn (TaxSlab $s) => [
                'width' => $s->width === null ? null : $this->money($s->width),
                'percent' => $this->money($s->percent),
            ])->all(),
            lateDivisor: (int) $settings->late_divisor,
            attendanceMinOffset: $this->oneDecimal($settings->attendance_min_offset),
            attendanceMaxOffset: $this->oneDecimal($settings->attendance_max_offset),
            hoursPerMonth: (int) $settings->hours_per_month,
            hoursPerDay: (int) $settings->hours_per_day,
            otMultiplier: $this->money($settings->ot_multiplier),
        );
    }

    public function setStatutoryRate(string $code, string $percent, string $from): StatutoryRate
    {
        Gate::authorize('payroll.config');
        $this->assertFirstOfMonth($from);
        $this->assertNotInLockedPeriod($from);
        $this->assertPercent($percent);

        return DB::transaction(function () use ($code, $percent, $from) {
            $this->assertAppendOnly(StatutoryRate::query()->where('code', $code), $from, 'code');

            $open = StatutoryRate::query()
                ->where('code', $code)
                ->whereNull('effective_to')
                ->lockForUpdate()
                ->first();

            if ($open !== null) {
                $open->update([
                    'effective_to' => CarbonImmutable::parse($from)->subDay()->toDateString(),
                ]);
            }

            return StatutoryRate::query()->create([
                'code' => $code,
                'percent' => $percent,
                'effective_from' => $from,
                'effective_to' => null,
            ]);
        });
    }

    /** @param  list<array{width: ?string, percent: string}>  $slabs */
    public function setTaxTable(string $from, array $slabs): void
    {
        Gate::authorize('payroll.config');
        $this->assertFirstOfMonth($from);
        $this->assertNotInLockedPeriod($from);
        $this->assertTaxSlabs($slabs);

        DB::transaction(function () use ($from, $slabs) {
            $latestFrom = TaxSlab::query()->max('effective_from');
            if ($latestFrom !== null) {
                $latest = CarbonImmutable::parse($latestFrom)->toDateString();
                if ($from <= $latest) {
                    if ($from === $latest) {
                        throw new InvalidPayRule('A tax table already starts on this date.');
                    }
                    throw new InvalidPayRule('A new tax table must start after the latest version.');
                }
            }

            TaxSlab::query()
                ->whereNull('effective_to')
                ->lockForUpdate()
                ->get()
                ->each(function (TaxSlab $row) use ($from) {
                    $row->update([
                        'effective_to' => CarbonImmutable::parse($from)->subDay()->toDateString(),
                    ]);
                });

            foreach (array_values($slabs) as $index => $slab) {
                TaxSlab::query()->create([
                    'effective_from' => $from,
                    'effective_to' => null,
                    'sequence' => $index + 1,
                    'width' => $slab['width'],
                    'percent' => $slab['percent'],
                ]);
            }
        });
    }

    public function setTier(string $code, string $name, string $base, string $perDay, string $from): AttendanceAllowanceTier
    {
        Gate::authorize('payroll.config');
        $this->assertFirstOfMonth($from);
        $this->assertNotInLockedPeriod($from);

        return DB::transaction(function () use ($code, $name, $base, $perDay, $from) {
            $this->assertAppendOnly(AttendanceAllowanceTier::query()->where('code', $code), $from, 'code');

            $open = AttendanceAllowanceTier::query()
                ->where('code', $code)
                ->whereNull('effective_to')
                ->lockForUpdate()
                ->first();

            if ($open !== null) {
                $open->update([
                    'effective_to' => CarbonImmutable::parse($from)->subDay()->toDateString(),
                ]);
            }

            return AttendanceAllowanceTier::query()->create([
                'code' => $code,
                'name' => $name,
                'base_amount' => $base,
                'per_day_rate' => $perDay,
                'effective_from' => $from,
                'effective_to' => null,
            ]);
        });
    }

    public function tier(string $code, \DateTimeInterface|string $on): ?AttendanceAllowanceTier
    {
        $day = $this->asDate($on);

        return AttendanceAllowanceTier::query()
            ->where('code', $code)
            ->whereDate('effective_from', '<=', $day)
            ->where(function ($q) use ($day) {
                $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $day);
            })
            ->first();
    }

    public function setExtraDayMethod(Grade $g, ExtraDayMethod $m, string $from): GradePayRule
    {
        Gate::authorize('payroll.config');
        $this->assertFirstOfMonth($from);
        $this->assertNotInLockedPeriod($from);

        return EffectiveDating::insert(new GradePayRule([
            'grade_id' => $g->id,
            'extra_day_method' => $m,
            'effective_from' => $from,
            'effective_to' => null,
        ]));
    }

    public function extraDayMethod(int $gradeId, \DateTimeInterface|string $on): ?ExtraDayMethod
    {
        $row = GradePayRule::query()
            ->where('grade_id', $gradeId)
            ->effectiveOn($on)
            ->first();

        return $row?->extra_day_method;
    }

    /** @param  array<string, mixed>  $settings */
    public function setCompanySettings(Company $c, array $settings, string $from): CompanyPaySetting
    {
        Gate::authorize('payroll.config');
        $this->assertFirstOfMonth($from);
        $this->assertNotInLockedPeriod($from, (int) $c->id);
        $validated = $this->validateCompanySettings($settings);

        return EffectiveDating::insert(new CompanyPaySetting([
            'company_id' => $c->id,
            ...$validated,
            'effective_from' => $from,
            'effective_to' => null,
        ]));
    }

    public function gradesWithoutRule(\DateTimeInterface|string $on): Collection
    {
        $day = $this->asDate($on);
        $gradedIds = GradePayRule::query()
            ->effectiveOn($day)
            ->pluck('grade_id');

        return Grade::query()
            ->where('is_active', true)
            ->whereNotIn('id', $gradedIds)
            ->orderBy('rank')
            ->get();
    }

    private function statutoryOn(string $code, string $day): ?StatutoryRate
    {
        return StatutoryRate::query()
            ->where('code', $code)
            ->whereDate('effective_from', '<=', $day)
            ->where(function ($q) use ($day) {
                $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $day);
            })
            ->first();
    }

    private function assertFirstOfMonth(string $from): void
    {
        $day = CarbonImmutable::parse($from);
        if ((int) $day->day !== 1) {
            throw new InvalidPayRule('A rule must start on the first day of a month.');
        }
    }

    private function assertNotInLockedPeriod(string $from, ?int $companyId = null): void
    {
        $q = PayRun::query()
            ->where('status', PayRunStatus::LOCKED)
            ->whereHas('payPeriod', function ($q) use ($from, $companyId) {
                $q->whereDate('end_date', '>=', $from);
                if ($companyId !== null) {
                    $q->where('company_id', $companyId);
                }
            });

        if ($q->exists()) {
            throw new InvalidPayRule('Pay rules cannot change on or before a locked pay period.');
        }
    }

    private function assertPercent(string $percent): void
    {
        $value = (float) $percent;
        if ($value < 0 || $value > 100) {
            throw new InvalidPayRule('Percent must be between 0 and 100.');
        }
    }

    /** @param  list<array{width: ?string, percent: string}>  $slabs */
    private function assertTaxSlabs(array $slabs): void
    {
        if ($slabs === []) {
            throw new InvalidPayRule('A tax table needs at least one slab.');
        }

        $nullIndexes = [];
        foreach (array_values($slabs) as $i => $slab) {
            $this->assertPercent((string) $slab['percent']);
            $width = $slab['width'] ?? null;
            if ($width === null || $width === '') {
                $nullIndexes[] = $i;
            } elseif ((float) $width <= 0) {
                throw new InvalidPayRule('Slab width must be greater than 0.');
            }
        }

        if (count($nullIndexes) !== 1) {
            throw new InvalidPayRule('A tax table needs exactly one open-ended last slab.');
        }

        if ($nullIndexes[0] !== count($slabs) - 1) {
            throw new InvalidPayRule('A tax table needs exactly one open-ended last slab.');
        }
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function validateCompanySettings(array $settings): array
    {
        $late = (int) ($settings['late_divisor'] ?? 0);
        if ($late <= 0) {
            throw new InvalidPayRule('Late divisor must be greater than 0.');
        }

        $min = (float) ($settings['attendance_min_offset'] ?? -1);
        $max = (float) ($settings['attendance_max_offset'] ?? -1);
        if ($min < 0 || $min > 10 || $max < 0 || $max > 10) {
            throw new InvalidPayRule('Attendance offsets must be between 0 and 10.');
        }

        $hoursMonth = (int) ($settings['hours_per_month'] ?? 0);
        if ($hoursMonth < 100 || $hoursMonth > 300) {
            throw new InvalidPayRule('Hours per month must be between 100 and 300.');
        }

        $hoursDay = (int) ($settings['hours_per_day'] ?? 0);
        if ($hoursDay < 1 || $hoursDay > 12) {
            throw new InvalidPayRule('Hours per day must be between 1 and 12.');
        }

        $ot = (float) ($settings['ot_multiplier'] ?? 0);
        if ($ot < 1.0 || $ot > 3.0) {
            throw new InvalidPayRule('OT multiplier must be between 1.00 and 3.00.');
        }

        return [
            'late_divisor' => $late,
            'attendance_min_offset' => $settings['attendance_min_offset'],
            'attendance_max_offset' => $settings['attendance_max_offset'],
            'hours_per_month' => $hoursMonth,
            'hours_per_day' => $hoursDay,
            'ot_multiplier' => $settings['ot_multiplier'],
        ];
    }

    private function assertAppendOnly($query, string $from, string $label): void
    {
        $latest = (clone $query)->orderByDesc('effective_from')->lockForUpdate()->value('effective_from');
        if ($latest === null) {
            return;
        }

        $latestDay = CarbonImmutable::parse($latest)->toDateString();
        if ($from === $latestDay) {
            throw new InvalidPayRule('A record already starts on this date.');
        }
        if ($from < $latestDay) {
            throw new InvalidPayRule('A new row must start after the latest existing row for that '.$label.'.');
        }
    }

    private function asDate(\DateTimeInterface|string $on): string
    {
        return CarbonImmutable::parse($on)->toDateString();
    }

    private function money(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    private function oneDecimal(mixed $value): string
    {
        return number_format((float) $value, 1, '.', '');
    }
}
