<?php

namespace App\Support\Effective;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/** The model must declare: protected string $effectiveOwnerKey = '<column>'; */
trait EffectiveDated
{
    /** Rows where effective_from <= $date AND (effective_to IS NULL OR effective_to >= $date). */
    public function scopeEffectiveOn(Builder $query, \DateTimeInterface|string $date): Builder
    {
        $day = $this->normalizeEffectiveDate($date);

        return $query
            ->whereDate('effective_from', '<=', $day)
            ->where(function (Builder $inner) use ($day) {
                $inner->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $day);
            });
    }

    /** effectiveOn(today in app timezone). */
    public function scopeCurrent(Builder $query): Builder
    {
        return $this->scopeEffectiveOn(
            $query,
            CarbonImmutable::now(config('app.timezone'))->toDateString(),
        );
    }

    public function effectiveOwnerKey(): string
    {
        return $this->effectiveOwnerKey;
    }

    private function normalizeEffectiveDate(\DateTimeInterface|string $date): string
    {
        if ($date instanceof \DateTimeInterface) {
            return $date->format('Y-m-d');
        }

        return (string) $date;
    }
}
