<?php

namespace App\Support\Audit;

final class AuditLogger
{
    /** @var list<string> */
    private static array $reasons = [];

    /** Everything audited inside $fn gets $reason. Nested calls: the innermost reason wins. Returns $fn's result. */
    public static function withReason(string $reason, \Closure $fn): mixed
    {
        self::$reasons[] = $reason;

        try {
            return $fn();
        } finally {
            array_pop(self::$reasons);
        }
    }

    public static function currentReason(): ?string
    {
        if (self::$reasons === []) {
            return null;
        }

        return self::$reasons[array_key_last(self::$reasons)];
    }
}
