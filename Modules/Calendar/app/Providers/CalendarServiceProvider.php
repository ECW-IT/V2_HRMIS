<?php

namespace Modules\Calendar\Providers;

use Livewire\Livewire;
use Modules\Calendar\Console\EnsurePayPeriods;
use Modules\Calendar\Livewire\ExpectedDaysGrid;
use Modules\Calendar\Livewire\HolidaysIndex;
use Modules\Calendar\Livewire\PayPeriodsIndex;
use Nwidart\Modules\Support\ModuleServiceProvider;

class CalendarServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Calendar';

    protected string $nameLower = 'calendar';

    protected array $commands = [
        EnsurePayPeriods::class,
    ];

    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function boot(): void
    {
        parent::boot();

        Livewire::component('calendar.holidays-index', HolidaysIndex::class);
        Livewire::component('calendar.pay-periods-index', PayPeriodsIndex::class);
        Livewire::component('calendar.expected-days-grid', ExpectedDaysGrid::class);
    }
}
