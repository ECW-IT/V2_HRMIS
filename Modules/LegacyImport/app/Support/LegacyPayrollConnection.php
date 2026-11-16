<?php

namespace Modules\LegacyImport\Support;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final class LegacyPayrollConnection
{
    public static function assertConfigured(): void
    {
        $database = config('database.connections.legacy_payroll.database');
        if (! is_string($database) || trim($database) === '') {
            throw new RuntimeException('The legacy_payroll connection is not configured.');
        }

        try {
            DB::connection('legacy_payroll')->getPdo();
        } catch (Throwable) {
            throw new RuntimeException('The legacy_payroll connection is not configured.');
        }
    }
}
