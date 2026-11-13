<?php

namespace Modules\LegacyImport\Console;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Modules\LegacyImport\Services\LegacySyncExporter;

class ExportLegacySyncCommand extends Command
{
    protected $signature = 'hrmis:export:legacy-sync {--since= : Start of the window (datetime)}';

    protected $description = 'Export employment changes for hand-entry into the legacy systems';

    public function handle(LegacySyncExporter $exporter): int
    {
        $sinceOption = $this->option('since');
        $since = is_string($sinceOption) && $sinceOption !== ''
            ? CarbonImmutable::parse($sinceOption, config('app.timezone'))
            : null;

        $run = $exporter->run($since);

        $this->info("Legacy sync run #{$run->id}");
        $this->info("Window: {$run->from_at} → {$run->to_at}");
        $this->info("Hires: {$run->new_hires}; Exits: {$run->exits}; Changes: {$run->changes}");

        if ($run->file_path === '') {
            $this->info('Nothing to report — no file written.');
        } else {
            $this->info("File: {$run->file_path}");
        }

        return self::SUCCESS;
    }
}
