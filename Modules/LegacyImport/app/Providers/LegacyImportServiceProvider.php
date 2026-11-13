<?php

namespace Modules\LegacyImport\Providers;

use Livewire\Livewire;
use Modules\LegacyImport\Console\ExportLegacySyncCommand;
use Modules\LegacyImport\Console\LoadCommand;
use Modules\LegacyImport\Console\ReportCommand;
use Modules\LegacyImport\Console\StageCommand;
use Modules\LegacyImport\Console\ValidateCommand;
use Modules\LegacyImport\Livewire\LegacySyncIndex;
use Nwidart\Modules\Support\ModuleServiceProvider;

class LegacyImportServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'LegacyImport';

    protected string $nameLower = 'legacy_import';

    protected array $commands = [
        StageCommand::class,
        ValidateCommand::class,
        LoadCommand::class,
        ReportCommand::class,
        ExportLegacySyncCommand::class,
    ];

    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function boot(): void
    {
        parent::boot();

        Livewire::component('legacy_import.legacy-sync-index', LegacySyncIndex::class);
    }
}
