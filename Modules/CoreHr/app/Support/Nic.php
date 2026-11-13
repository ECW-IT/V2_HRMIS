<?php

namespace Modules\CoreHr\Support;

use Carbon\CarbonImmutable;
use Modules\CoreHr\Enums\Gender;
use Modules\CoreHr\Exceptions\InvalidNic;

final class Nic
{
    public static function normalise(string $raw): string
    {
        $value = mb_strtoupper(trim($raw));

        if (preg_match('/^\d{12}$/', $value) === 1) {
            self::assertDayNumberValid(self::dayNumber($value));

            return $value;
        }

        if (preg_match('/^(\d{9})([VX])$/', $value, $matches) === 1) {
            $nine = $matches[1];
            $yy = substr($nine, 0, 2);
            $ddd = substr($nine, 2, 3);
            $sssc = substr($nine, 5, 4);
            $normalised = '19'.$yy.$ddd.'0'.$sssc;
            self::assertDayNumberValid(self::dayNumber($normalised));

            return $normalised;
        }

        throw new InvalidNic('NIC format is invalid.');
    }

    public static function dateOfBirth(string $normalised): CarbonImmutable
    {
        $year = (int) substr($normalised, 0, 4);
        $dayNumber = self::dayNumber($normalised);
        if ($dayNumber > 500) {
            $dayNumber -= 500;
        }

        $monthLengths = [31, 29, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        $remaining = $dayNumber;

        for ($month = 1; $month <= 12; $month++) {
            $length = $monthLengths[$month - 1];
            if ($remaining <= $length) {
                if ($month === 2 && $remaining === 29 && ! CarbonImmutable::create($year, 1, 1)->isLeapYear()) {
                    throw new InvalidNic('NIC day number is invalid for a non-leap year.');
                }

                return CarbonImmutable::create($year, $month, $remaining)->startOfDay();
            }
            $remaining -= $length;
        }

        throw new InvalidNic('NIC day number is invalid.');
    }

    public static function gender(string $normalised): Gender
    {
        return self::dayNumber($normalised) > 500 ? Gender::FEMALE : Gender::MALE;
    }

    private static function dayNumber(string $normalised): int
    {
        return (int) substr($normalised, 4, 3);
    }

    private static function assertDayNumberValid(int $dayNumber): void
    {
        $ok = ($dayNumber >= 1 && $dayNumber <= 366) || ($dayNumber >= 501 && $dayNumber <= 866);
        if (! $ok) {
            throw new InvalidNic('NIC day number is invalid.');
        }
    }
}
