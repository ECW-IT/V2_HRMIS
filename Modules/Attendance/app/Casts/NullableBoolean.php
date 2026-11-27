<?php

namespace Modules\Attendance\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * @implements CastsAttributes<?bool, ?int>
 */
final class NullableBoolean implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?bool
    {
        if ($value === null) {
            return null;
        }

        return (bool) $value;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?int
    {
        if ($value === null) {
            return null;
        }

        return $value ? 1 : 0;
    }
}
