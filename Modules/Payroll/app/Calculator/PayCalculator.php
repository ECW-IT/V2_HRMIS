<?php

namespace Modules\Payroll\Calculator;

use Modules\Payroll\Enums\CalcMethod;
use Modules\Payroll\Enums\ComponentKind;
use Modules\Payroll\Enums\ExtraDayMethod;
use Modules\Payroll\Exceptions\PayCalculationError;
use Modules\Payroll\Support\Money;

final class PayCalculator
{
    private const ITEM_METHODS = [
        CalcMethod::FIXED,
        CalcMethod::PROPORTIONAL,
        CalcMethod::ENTRY,
        CalcMethod::QUANTITY_RATE,
        CalcMethod::NIGHT_UNITS,
    ];

    private const FORBIDDEN_OVERRIDES = ['CF', 'ADVANCE', 'LOAN', 'EPF_ER', 'ETF_ER'];

    private const POST_OVERRIDES = ['LATE', 'EPF_EE', 'APIT'];

    public function calculate(PayInput $in): PayResult
    {
        $lines = [];
        $B = Money::toCents($in->basicSalary);
        $E = Money::toHundredths($in->expectedDays);
        $C = Money::toHundredths($in->creditedDays);
        $X = Money::toHundredths($in->extraDays);

        $nightHoursHundredths = $this->nightHoursHundredths($in);

        $this->addBasic($lines, $in, $B, $E, $C);
        $this->addOt($lines, $in, $nightHoursHundredths);
        $this->addExtraDay($lines, $in, $B, $E, $X);
        $this->addAttendance($lines, $in, $E, $C);
        $this->addItems($lines, $in, $E, $C);

        $this->applyEarlyOverrides($lines, $in);

        [$gross1, $gross2, $gross, $epfBaseCents, $taxableEarnCents] = $this->sumEarnings($lines, $in);

        if ($in->epfLiable) {
            $this->addStatutory($lines, $in, $epfBaseCents);
        } else {
            $epfBaseCents = 0;
        }

        $lateCents = $this->addLate($lines, $in, $B);
        $taxableCents = $taxableEarnCents - $lateCents;
        if ($taxableCents < 0) {
            $taxableCents = 0;
        }
        $this->addTax($lines, $in, $taxableCents);

        $this->addCarriedForward($lines, $in);
        $this->addAdvancesAndLoans($lines, $in);

        $this->applyPostOverrides($lines, $in);

        $this->sortLines($lines, $in);

        $totalDed = 0;
        $employer = 0;
        foreach ($lines as $line) {
            $cents = Money::toCents($line->amount);
            if ($line->kind === ComponentKind::DEDUCTION) {
                $totalDed += $cents;
            } elseif ($line->kind === ComponentKind::EMPLOYER) {
                $employer += $cents;
            }
        }

        if ($totalDed <= $gross) {
            $net = $gross - $totalDed;
            $cfOut = 0;
        } else {
            $net = 0;
            $cfOut = $totalDed - $gross;
        }

        return new PayResult(
            lines: $lines,
            grossSheet1: Money::fromCents($gross1),
            grossSheet2: Money::fromCents($gross2),
            gross: Money::fromCents($gross),
            totalDeductions: Money::fromCents($totalDed),
            netPay: Money::fromCents($net),
            carriedForwardOut: Money::fromCents($cfOut),
            epfBase: Money::fromCents($epfBaseCents),
            taxablePay: Money::fromCents($taxableCents),
            employerTotal: Money::fromCents($employer),
        );
    }

    private function nightHoursHundredths(PayInput $in): int
    {
        $sum = 0;
        foreach ($in->items as $item) {
            $spec = $in->components[$item->code] ?? null;
            if ($spec === null || $spec->method !== CalcMethod::NIGHT_UNITS) {
                continue;
            }
            if ($item->quantity === null || Money::toHundredths($item->quantity) === 0) {
                continue;
            }
            $sum += Money::toHundredths($item->quantity);
        }

        return $sum;
    }

    /** @param  list<PayLine>  $lines */
    private function addBasic(array &$lines, PayInput $in, int $B, int $E, int $C): void
    {
        if ($B === 0) {
            return;
        }

        $amount = ($E > 0 && $C < $E) ? Money::ratio($B, $C, $E) : $B;
        if ($amount === 0) {
            return;
        }

        $spec = $in->components['BASIC'];
        $lines[] = $this->line($spec, Money::fromCents($C), Money::fromCents($B), Money::fromCents($amount));
    }

    /** @param  list<PayLine>  $lines */
    private function addOt(array &$lines, PayInput $in, int $nightHoursHundredths): void
    {
        // night hours × 60 minutes: hundredths-of-hours * 60 / 100
        $nightMinutes = Money::ratio($nightHoursHundredths, 60, 100);
        $payableMinutes = $in->otMinutes - $nightMinutes;
        if ($payableMinutes <= 0) {
            return;
        }

        $rate = Money::toCents($in->otRate);
        $amount = Money::ratio($rate, $payableMinutes, 60);
        if ($amount === 0) {
            return;
        }

        $qtyHundredths = Money::ratio($payableMinutes, 100, 60);
        $spec = $in->components['OT'];
        $lines[] = $this->line($spec, Money::fromCents($qtyHundredths), $in->otRate, Money::fromCents($amount));
    }

    /** @param  list<PayLine>  $lines */
    private function addExtraDay(array &$lines, PayInput $in, int $B, int $E, int $X): void
    {
        if ($X <= 0) {
            return;
        }

        $spec = $in->components['EXTRA_DAY'];
        $rules = $in->rules;

        if ($B === 0) {
            $amount = Money::ratio(Money::toCents($in->otRate) * $rules->hoursPerDay, $X, 100);
        } elseif ($in->extraDayMethod === null) {
            throw new PayCalculationError('No extra-day method for this grade.');
        } elseif ($in->extraDayMethod === ExtraDayMethod::HOURLY) {
            // B × otMultiplier × hoursPerDay × X ÷ hoursPerMonth
            // = ratio(B * hoursPerDay * X_hundredths * otMult_cents, 1, hoursPerMonth * 100 * 100)
            // otMultiplier is e.g. 1.50 → 150 cents-of-unit
            $mult = Money::toCents($rules->otMultiplier);
            $num = $B * $rules->hoursPerDay * $X * $mult;
            $den = $rules->hoursPerMonth * 100 * 100;
            $amount = Money::ratio($num, 1, $den);
        } else {
            // DAILY: B × otMultiplier × X ÷ E
            $mult = Money::toCents($rules->otMultiplier);
            $num = $B * $X * $mult;
            $den = $E * 100;
            $amount = Money::ratio($num, 1, $den);
        }

        if ($amount === 0) {
            return;
        }

        $lines[] = $this->line($spec, Money::fromCents($X), null, Money::fromCents($amount));
    }

    /** @param  list<PayLine>  $lines */
    private function addAttendance(array &$lines, PayInput $in, int $E, int $C): void
    {
        if ($in->tier === null) {
            return;
        }

        $minOff = Money::toHundredths($in->rules->attendanceMinOffset);
        $maxOff = Money::toHundredths($in->rules->attendanceMaxOffset);

        if ($C < $E - $minOff) {
            return;
        }

        $cap = $E + $maxOff;
        $capped = $C < $cap ? $C : $cap;
        $whole = intdiv($capped, 100) * 100;

        $base = Money::toCents($in->tier->baseAmount);
        $perDay = Money::toCents($in->tier->perDayRate);
        $diff = $whole - $E; // hundredths of a day
        $amount = $base + Money::ratio($perDay, $diff, 100);
        if ($amount < 0) {
            $amount = 0;
        }
        if ($amount === 0) {
            return;
        }

        $spec = $in->components['ATT_ALLOW'];
        $lines[] = $this->line($spec, null, null, Money::fromCents($amount));
    }

    /** @param  list<PayLine>  $lines */
    private function addItems(array &$lines, PayInput $in, int $E, int $C): void
    {
        $nightItems = [];
        $otherItems = [];

        foreach ($in->items as $item) {
            $spec = $in->components[$item->code] ?? null;
            if ($spec === null) {
                throw new PayCalculationError('Unknown pay component: '.$item->code);
            }
            if (! in_array($spec->method, self::ITEM_METHODS, true)) {
                throw new PayCalculationError('Calculator component cannot be supplied as an item: '.$item->code);
            }
            if ($spec->method === CalcMethod::NIGHT_UNITS) {
                $nightItems[] = [$item, $spec];
            } else {
                $otherItems[] = [$item, $spec];
            }
        }

        usort($nightItems, fn ($a, $b) => $a[1]->displayOrder <=> $b[1]->displayOrder);

        $reductionApplied = false;
        foreach ($nightItems as [$item, $spec]) {
            $line = $this->nightLine($item, $spec, $E, $C, ! $reductionApplied);
            if ($line !== null) {
                if (! $reductionApplied && $item->quantity !== null && Money::toHundredths($item->quantity) > 0) {
                    $reductionApplied = true;
                }
                $lines[] = $line;
            }
        }

        foreach ($otherItems as [$item, $spec]) {
            $line = $this->itemLine($item, $spec, $E, $C);
            if ($line !== null) {
                $lines[] = $line;
            }
        }
    }

    private function nightLine(PayItem $item, ComponentSpec $spec, int $E, int $C, bool $applyReduction): ?PayLine
    {
        if ($item->quantity === null || $item->amount === null) {
            return null;
        }
        $hours = Money::toHundredths($item->quantity);
        if ($hours === 0) {
            return null;
        }

        $rate = Money::toCents($item->amount);
        $base = $spec->nightBaseHours ?? 0;
        if ($base <= 0) {
            throw new PayCalculationError('Night component is missing base hours.');
        }

        // full = rate × hours ÷ base  (hours and result in consistent units)
        // hours is hundredths; base is whole hours → full = rate * hours / (base * 100)
        $full = Money::ratio($rate, $hours, $base * 100);

        if ($C >= $E || ! $applyReduction) {
            $amount = $full;
        } else {
            // units = hours ÷ base (as hundredths of a unit): hours_hundredths / base
            // short days = E - C (hundredths)
            // reduced units = max(0, units - shortDays) where units and shortDays are in "days"?
            // Task: max( rate × max(0, units − (E − C)), full ÷ 2 )
            // units = hours ÷ baseHours (e.g. 20/5 = 4). E-C in days (e.g. 1 day).
            // So units is dimensionless nights; (E-C) is days in hundredths.
            // unitsHundredths = hours / base  → Money::ratio(hours, 1, base) gives hundredths of units?
            // hours=2000 (20.00), base=5 → units = 4.00 → 400 hundredths
            $unitsHundredths = Money::ratio($hours, 1, $base);
            $short = $E - $C;
            $reducedUnits = $unitsHundredths - $short;
            if ($reducedUnits < 0) {
                $reducedUnits = 0;
            }
            $reduced = Money::ratio($rate, $reducedUnits, 100);
            $half = Money::ratio($full, 1, 2);
            $amount = $reduced > $half ? $reduced : $half;
        }

        if ($amount === 0) {
            return null;
        }

        return $this->line(
            $spec,
            Money::fromCents($hours),
            $item->amount,
            Money::fromCents($amount),
            $item->sourceType,
            $item->sourceId,
        );
    }

    private function itemLine(PayItem $item, ComponentSpec $spec, int $E, int $C): ?PayLine
    {
        $amountCents = 0;
        $quantity = null;
        $rate = null;

        switch ($spec->method) {
            case CalcMethod::FIXED:
            case CalcMethod::ENTRY:
                if ($item->amount === null) {
                    return null;
                }
                $amountCents = Money::toCents($item->amount);
                break;

            case CalcMethod::PROPORTIONAL:
                if ($item->amount === null) {
                    return null;
                }
                $raw = Money::toCents($item->amount);
                $amountCents = ($E > 0 && $C < $E) ? Money::ratio($raw, $C, $E) : $raw;
                break;

            case CalcMethod::QUANTITY_RATE:
                if ($item->quantity === null || Money::toHundredths($item->quantity) === 0) {
                    return null;
                }
                if ($item->amount === null) {
                    return null;
                }
                $q = Money::toHundredths($item->quantity);
                $r = Money::toCents($item->amount);
                $amountCents = Money::ratio($r, $q, 100);
                $quantity = Money::fromCents($q);
                $rate = $item->amount;
                break;

            default:
                return null;
        }

        if ($amountCents === 0) {
            return null;
        }

        return $this->line(
            $spec,
            $quantity,
            $rate,
            Money::fromCents($amountCents),
            $item->sourceType,
            $item->sourceId,
        );
    }

    /**
     * @param  list<PayLine>  $lines
     * @return array{0:int,1:int,2:int,3:int,4:int}
     */
    private function sumEarnings(array $lines, PayInput $in): array
    {
        $g1 = 0;
        $g2 = 0;
        $epf = 0;
        $taxable = 0;

        foreach ($lines as $line) {
            if ($line->kind !== ComponentKind::EARNING) {
                continue;
            }
            $cents = Money::toCents($line->amount);
            if ($line->sheet === 1) {
                $g1 += $cents;
            } else {
                $g2 += $cents;
            }
            $spec = $in->components[$line->code];
            if ($in->epfLiable && $spec->epfLiable) {
                $epf += $cents;
            }
            if ($spec->taxable) {
                $taxable += $cents;
            }
        }

        return [$g1, $g2, $g1 + $g2, $epf, $taxable];
    }

    /** @param  list<PayLine>  $lines */
    private function addStatutory(array &$lines, PayInput $in, int $epfBaseCents): void
    {
        if ($epfBaseCents === 0) {
            // Still produce 0? Task: amount 0 not produced. So skip if zero.
            return;
        }

        $ee = Money::ratio($epfBaseCents, Money::toCents($in->rules->epfEmployeePercent), 10000);
        $er = Money::ratio($epfBaseCents, Money::toCents($in->rules->epfEmployerPercent), 10000);
        $etf = Money::ratio($epfBaseCents, Money::toCents($in->rules->etfPercent), 10000);

        if ($ee !== 0) {
            $lines[] = $this->line($in->components['EPF_EE'], null, null, Money::fromCents($ee));
        }
        if ($er !== 0) {
            $lines[] = $this->line($in->components['EPF_ER'], null, null, Money::fromCents($er));
        }
        if ($etf !== 0) {
            $lines[] = $this->line($in->components['ETF_ER'], null, null, Money::fromCents($etf));
        }
    }

    /** @param  list<PayLine>  $lines */
    private function addLate(array &$lines, PayInput $in, int $B): int
    {
        if ($in->lateMinutesChargeable <= 0 || $B === 0) {
            return 0;
        }

        $amount = Money::ratio($B, $in->lateMinutesChargeable, $in->rules->lateDivisor);
        if ($amount === 0) {
            return 0;
        }

        $lines[] = $this->line(
            $in->components['LATE'],
            (string) $in->lateMinutesChargeable,
            null,
            Money::fromCents($amount),
        );

        return $amount;
    }

    /** @param  list<PayLine>  $lines */
    private function addTax(array &$lines, PayInput $in, int $taxableCents): void
    {
        $tax = TaxTable::taxOn($taxableCents, $in->rules->taxSlabs);
        if ($tax === 0) {
            return;
        }

        $lines[] = $this->line($in->components['APIT'], null, null, Money::fromCents($tax));
    }

    /** @param  list<PayLine>  $lines */
    private function addCarriedForward(array &$lines, PayInput $in): void
    {
        $cf = Money::toCents($in->carriedForwardIn);
        if ($cf <= 0) {
            return;
        }

        $lines[] = $this->line($in->components['CF'], null, null, Money::fromCents($cf));
    }

    /** @param  list<PayLine>  $lines */
    private function addAdvancesAndLoans(array &$lines, PayInput $in): void
    {
        foreach ($in->advances as $item) {
            if ($item->amount === null) {
                continue;
            }
            $cents = Money::toCents($item->amount);
            if ($cents === 0) {
                continue;
            }
            $lines[] = $this->line(
                $in->components['ADVANCE'],
                null,
                null,
                Money::fromCents($cents),
                $item->sourceType,
                $item->sourceId,
            );
        }

        foreach ($in->loanInstalments as $item) {
            if ($item->amount === null) {
                continue;
            }
            $cents = Money::toCents($item->amount);
            if ($cents === 0) {
                continue;
            }
            $lines[] = $this->line(
                $in->components['LOAN'],
                null,
                null,
                Money::fromCents($cents),
                $item->sourceType,
                $item->sourceId,
            );
        }
    }

    /** @param  list<PayLine>  $lines */
    private function applyEarlyOverrides(array &$lines, PayInput $in): void
    {
        foreach ($in->overrides as $code => $amount) {
            if (in_array($code, self::POST_OVERRIDES, true)) {
                continue;
            }
            $this->applyOverride($lines, $in, $code, $amount);
        }
    }

    /** @param  list<PayLine>  $lines */
    private function applyPostOverrides(array &$lines, PayInput $in): void
    {
        foreach ($in->overrides as $code => $amount) {
            if (! in_array($code, self::POST_OVERRIDES, true)) {
                continue;
            }
            $this->applyOverride($lines, $in, $code, $amount);
        }
    }

    /** @param  list<PayLine>  $lines */
    private function applyOverride(array &$lines, PayInput $in, string $code, string $amount): void
    {
        if (in_array($code, self::FORBIDDEN_OVERRIDES, true) || ! isset($in->components[$code])) {
            throw new PayCalculationError('Override not allowed for: '.$code);
        }

        $cents = Money::toCents($amount);
        if ($cents < 0) {
            throw new PayCalculationError('Override amount cannot be negative.');
        }

        $spec = $in->components[$code];
        $lines = array_values(array_filter($lines, fn (PayLine $l) => $l->code !== $code));
        $lines[] = new PayLine(
            code: $spec->code,
            kind: $spec->kind,
            sheet: $spec->sheet,
            quantity: null,
            rate: null,
            amount: Money::fromCents($cents),
            sourceType: null,
            sourceId: null,
            isOverride: true,
            note: null,
            displayOrder: $spec->displayOrder,
        );
    }

    /** @param  list<PayLine>  $lines */
    private function sortLines(array &$lines, PayInput $in): void
    {
        usort($lines, function (PayLine $a, PayLine $b) {
            if ($a->displayOrder === $b->displayOrder) {
                return 0;
            }

            return $a->displayOrder <=> $b->displayOrder;
        });
    }

    private function line(
        ComponentSpec $spec,
        ?string $quantity,
        ?string $rate,
        string $amount,
        ?string $sourceType = null,
        ?int $sourceId = null,
        bool $isOverride = false,
        ?string $note = null,
    ): PayLine {
        return new PayLine(
            code: $spec->code,
            kind: $spec->kind,
            sheet: $spec->sheet,
            quantity: $quantity,
            rate: $rate,
            amount: $amount,
            sourceType: $sourceType,
            sourceId: $sourceId,
            isOverride: $isOverride,
            note: $note,
            displayOrder: $spec->displayOrder,
        );
    }
}
