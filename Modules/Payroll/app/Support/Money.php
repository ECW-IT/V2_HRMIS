<?php

namespace Modules\Payroll\Support;

final class Money
{
    public static function toCents(string $amount): int
    {
        return self::toScaled($amount);
    }

    public static function fromCents(int $cents): string
    {
        $negative = $cents < 0;
        $abs = $negative ? -$cents : $cents;
        $whole = intdiv($abs, 100);
        $frac = $abs % 100;
        $fracStr = $frac < 10 ? '0'.$frac : (string) $frac;
        $out = $whole.'.'.$fracStr;

        return $negative ? '-'.$out : $out;
    }

    public static function toHundredths(string $quantity): int
    {
        return self::toScaled($quantity);
    }

    /** round_half_up($a × $num ÷ $den). $den > 0. Half-up away from zero for negatives. Integer arithmetic only. */
    public static function ratio(int $a, int $num, int $den): int
    {
        if ($den <= 0) {
            throw new \InvalidArgumentException('Denominator must be greater than zero.');
        }

        $prod = $a * $num;
        $quot = intdiv($prod, $den);
        $rem = $prod % $den;

        if ($rem === 0) {
            return $quot;
        }

        if (abs($rem) * 2 >= $den) {
            return $prod >= 0 ? $quot + 1 : $quot - 1;
        }

        return $quot;
    }

    private static function toScaled(string $value): int
    {
        if (! preg_match('/^-?\d+(\.\d{1,2})?$/', $value)) {
            throw new \InvalidArgumentException('Value must be a plain decimal with at most two places.');
        }

        $negative = str_starts_with($value, '-');
        $raw = $negative ? substr($value, 1) : $value;
        $parts = explode('.', $raw, 2);
        $whole = (int) $parts[0];
        $frac = $parts[1] ?? '';
        if (strlen($frac) === 0) {
            $frac = '00';
        } elseif (strlen($frac) === 1) {
            $frac .= '0';
        }
        $scaled = $whole * 100 + (int) $frac;

        return $negative ? -$scaled : $scaled;
    }
}
