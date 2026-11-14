<?php

namespace Modules\Attendance\Support;

use Modules\Attendance\Contracts\LeaveDayProvider;

final class NullLeaveDayProvider implements LeaveDayProvider
{
    public function forDay(int $employmentId, string $date): ?array
    {
        return null;
    }
}
