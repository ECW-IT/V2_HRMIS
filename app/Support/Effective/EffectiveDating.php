<?php

namespace App\Support\Effective;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final class EffectiveDating
{
    /** Insert $new (unsaved, effective_from set) among its owner's rows. See Rules. Returns the saved model. */
    public static function insert(Model $new): Model
    {
        return DB::transaction(function () use ($new) {
            /** @var Model&object{effectiveOwnerKey(): string} $new */
            $ownerKey = $new->effectiveOwnerKey();
            $ownerId = (int) $new->getAttribute($ownerKey);
            $from = self::asDateString($new->getAttribute('effective_from'));
            $to = self::nullableDateString($new->getAttribute('effective_to'));

            $locked = $new->newQuery()
                ->where($ownerKey, $ownerId)
                ->orderBy('effective_from')
                ->lockForUpdate()
                ->get();

            if ($locked->contains(fn (Model $row) => self::asDateString($row->getAttribute('effective_from')) === $from)) {
                throw new EffectiveDateConflict('A record already starts on this date.');
            }

            if ($to !== null && $to < $from) {
                throw new EffectiveDateConflict('effective_to cannot be before effective_from.');
            }

            $containing = $locked->first(function (Model $row) use ($from) {
                $rowFrom = self::asDateString($row->getAttribute('effective_from'));
                $rowTo = self::nullableDateString($row->getAttribute('effective_to'));

                return $rowFrom <= $from && ($rowTo === null || $rowTo >= $from);
            });

            if ($containing !== null) {
                $containing->setAttribute(
                    'effective_to',
                    CarbonImmutable::createFromFormat('Y-m-d', $from)->subDay()->toDateString(),
                );
                $containing->save();
            }

            $next = $locked
                ->filter(fn (Model $row) => self::asDateString($row->getAttribute('effective_from')) > $from)
                ->sortBy(fn (Model $row) => self::asDateString($row->getAttribute('effective_from')))
                ->first();

            if ($next !== null) {
                $new->setAttribute(
                    'effective_to',
                    CarbonImmutable::createFromFormat('Y-m-d', self::asDateString($next->getAttribute('effective_from')))
                        ->subDay()
                        ->toDateString(),
                );
            }

            $new->save();

            return $new;
        });
    }

    /** Set effective_to on the owner's open row. Throws EffectiveDateConflict if $on is before that row's effective_from. No-op if there is no open row. */
    public static function close(string $modelClass, int $ownerId, \DateTimeInterface|string $on): void
    {
        DB::transaction(function () use ($modelClass, $ownerId, $on) {
            /** @var Model&object{effectiveOwnerKey(): string} $instance */
            $instance = new $modelClass;
            $ownerKey = $instance->effectiveOwnerKey();
            $onDate = self::asDateString($on);

            $open = $modelClass::query()
                ->where($ownerKey, $ownerId)
                ->whereNull('effective_to')
                ->lockForUpdate()
                ->first();

            if ($open === null) {
                return;
            }

            $from = self::asDateString($open->getAttribute('effective_from'));

            if ($onDate < $from) {
                throw new EffectiveDateConflict('Cannot close before the row starts.');
            }

            $open->setAttribute('effective_to', $onDate);
            $open->save();
        });
    }

    /** Throws EffectiveDateConflict if any two rows of the owner overlap or a closed row has effective_to < effective_from. */
    public static function assertNoOverlap(string $modelClass, int $ownerId): void
    {
        /** @var Model&object{effectiveOwnerKey(): string} $instance */
        $instance = new $modelClass;
        $ownerKey = $instance->effectiveOwnerKey();

        $rows = $modelClass::query()
            ->where($ownerKey, $ownerId)
            ->orderBy('effective_from')
            ->get();

        foreach ($rows as $row) {
            $from = self::asDateString($row->getAttribute('effective_from'));
            $to = self::nullableDateString($row->getAttribute('effective_to'));

            if ($to !== null && $to < $from) {
                throw new EffectiveDateConflict('A closed row has effective_to before effective_from.');
            }
        }

        for ($i = 0; $i < $rows->count(); $i++) {
            for ($j = $i + 1; $j < $rows->count(); $j++) {
                $aFrom = self::asDateString($rows[$i]->getAttribute('effective_from'));
                $aTo = self::nullableDateString($rows[$i]->getAttribute('effective_to'));
                $bFrom = self::asDateString($rows[$j]->getAttribute('effective_from'));
                $bTo = self::nullableDateString($rows[$j]->getAttribute('effective_to'));

                if (self::rangesOverlap($aFrom, $aTo, $bFrom, $bTo)) {
                    throw new EffectiveDateConflict('Effective date ranges overlap.');
                }
            }
        }
    }

    private static function rangesOverlap(string $aFrom, ?string $aTo, string $bFrom, ?string $bTo): bool
    {
        $aEnd = $aTo ?? '9999-12-31';
        $bEnd = $bTo ?? '9999-12-31';

        return $aFrom <= $bEnd && $bFrom <= $aEnd;
    }

    private static function asDateString(\DateTimeInterface|string $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return (string) $value;
    }

    private static function nullableDateString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return self::asDateString($value);
    }
}
