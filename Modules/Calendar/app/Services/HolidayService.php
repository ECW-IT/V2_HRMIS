<?php

namespace Modules\Calendar\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Calendar\Enums\HolidayKind;
use Modules\Calendar\Exceptions\InvalidHoliday;
use Modules\Calendar\Models\Holiday;
use Modules\Organisation\Models\Company;

final class HolidayService
{
    public function add(Company $c, \DateTimeInterface|string $date, string $name, HolidayKind $kind): Holiday
    {
        $normalized = $this->normalizeDate($date);

        if ($this->isDateLocked($c->id, $normalized)) {
            throw new InvalidHoliday('This date is inside a closed pay period and cannot be changed.');
        }

        $exists = Holiday::withoutGlobalScopes()
            ->where('company_id', $c->id)
            ->whereDate('date', $normalized)
            ->exists();

        if ($exists) {
            throw new InvalidHoliday('A holiday already exists on this date.');
        }

        return Holiday::query()->create([
            'company_id' => $c->id,
            'date' => $normalized,
            'name' => $name,
            'kind' => $kind,
        ]);
    }

    public function remove(Holiday $holiday): void
    {
        if ($this->isDateLocked((int) $holiday->company_id, $holiday->date)) {
            throw new InvalidHoliday('This date is inside a closed pay period and cannot be changed.');
        }

        $holiday->delete();
    }

    public function on(int $companyId, \DateTimeInterface|string $date): ?Holiday
    {
        return Holiday::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereDate('date', $this->normalizeDate($date))
            ->first();
    }

    /** @return array<string, Holiday> keyed by 'Y-m-d' */
    public function between(int $companyId, \DateTimeInterface|string $from, \DateTimeInterface|string $to): array
    {
        $fromDate = $this->normalizeDate($from);
        $toDate = $this->normalizeDate($to);

        $rows = Holiday::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereDate('date', '>=', $fromDate)
            ->whereDate('date', '<=', $toDate)
            ->orderBy('date')
            ->get();

        $keyed = [];
        foreach ($rows as $holiday) {
            $keyed[$holiday->date->format('Y-m-d')] = $holiday;
        }

        return $keyed;
    }

    /** Copies every holiday of $year from one company to another, skipping dates that already exist. Returns rows added. */
    public function copyYear(Company $from, Company $to, int $year): int
    {
        return (int) DB::transaction(function () use ($from, $to, $year) {
            $source = Holiday::withoutGlobalScopes()
                ->where('company_id', $from->id)
                ->whereYear('date', $year)
                ->orderBy('date')
                ->get();

            $existingDates = Holiday::withoutGlobalScopes()
                ->where('company_id', $to->id)
                ->whereYear('date', $year)
                ->get()
                ->map(fn (Holiday $h) => $h->date->format('Y-m-d'))
                ->all();

            $added = 0;

            foreach ($source as $holiday) {
                $dateKey = $holiday->date->format('Y-m-d');
                if (in_array($dateKey, $existingDates, true)) {
                    continue;
                }

                if ($this->isDateLocked($to->id, $dateKey)) {
                    throw new InvalidHoliday('This date is inside a closed pay period and cannot be changed.');
                }

                Holiday::query()->create([
                    'company_id' => $to->id,
                    'date' => $dateKey,
                    'name' => $holiday->name,
                    'kind' => $holiday->kind,
                ]);

                $added++;
            }

            return $added;
        });
    }

    public function isDateLocked(int $companyId, \DateTimeInterface|string $date): bool
    {
        return false;
    }

    private function normalizeDate(\DateTimeInterface|string $date): string
    {
        return Carbon::parse($date)->format('Y-m-d');
    }
}
