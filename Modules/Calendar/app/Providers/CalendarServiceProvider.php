<?php

namespace Modules\Calendar\Providers;

use Livewire\Livewire;
use Modules\Calendar\Livewire\HolidaysIndex;
use Nwidart\Modules\Support\ModuleServiceProvider;

class CalendarServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Calendar';

    protected string $nameLower = 'calendar';

    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function boot(): void
    {
        parent::boot();

        Livewire::component('calendar.holidays-index', HolidaysIndex::class);
    }
}
