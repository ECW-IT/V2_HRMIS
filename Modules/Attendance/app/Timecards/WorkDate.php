<?php

namespace Modules\Attendance\Timecards;

use Carbon\CarbonImmutable;

final class WorkDate
{
    /** The work date a punch belongs to: date of (punchedAt minus day_start_hour hours). */
    public static function of(\DateTimeInterface $punchedAt): string
    {
        return CarbonImmutable::instance($punchedAt)
            ->timezone(self::timezone())
            ->subHours(self::dayStartHour())
            ->toDateString();
    }

    /**
     * [start, end) datetimes that belong to a work date: [date 05:00:00, next day 05:00:00).
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function window(string $workDate): array
    {
        $hour = self::dayStartHour();
        $from = CarbonImmutable::parse($workDate, self::timezone())->setTime($hour, 0, 0);
        $to = $from->addDay();

        return [$from, $to];
    }

    private static function dayStartHour(): int
    {
        return self::configValue('attendance.day_start_hour', 5);
    }

    private static function timezone(): string
    {
        return (string) self::configValue('app.timezone', 'Asia/Colombo');
    }

    private static function configValue(string $key, mixed $default): mixed
    {
        if (! function_exists('app') || ! app()->bound('config')) {
            return $default;
        }

        return config($key, $default);
    }
}
