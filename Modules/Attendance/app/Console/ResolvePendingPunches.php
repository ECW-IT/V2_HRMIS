<?php

namespace Modules\Attendance\Console;

use Illuminate\Console\Command;
use Modules\Attendance\Services\PunchResolver;

class ResolvePendingPunches extends Command
{
    protected $signature = 'attendance:resolve-pending {--group= : Limit to one device group}';

    protected $description = 'Resolve UNRESOLVED punches against current enrolments';

    public function handle(PunchResolver $resolver): int
    {
        $group = $this->option('group');
        $group = is_string($group) && $group !== '' ? $group : null;
        $count = $resolver->resolvePending($group);
        $this->info("Resolved {$count} punches.");

        return self::SUCCESS;
    }
}
