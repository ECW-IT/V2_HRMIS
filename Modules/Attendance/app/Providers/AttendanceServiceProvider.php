<?php

namespace Modules\Attendance\Providers;

use Livewire\Livewire;
use Modules\Attendance\Console\ComputeTimecards;
use Modules\Attendance\Console\ResolvePendingPunches;
use Modules\Attendance\Http\Middleware\AuthenticateDevice;
use Modules\Attendance\Livewire\DevicesIndex;
use Modules\Attendance\Livewire\EmployeePunches;
use Modules\Attendance\Livewire\ShiftsIndex;
use Modules\Attendance\Livewire\Tabs\TimeProfile;
use Modules\Attendance\Livewire\TimeProfilesBulk;
use Modules\Attendance\Livewire\UnresolvedPunches;
use Nwidart\Modules\Support\ModuleServiceProvider;

class AttendanceServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Attendance';

    protected string $nameLower = 'attendance';

    protected array $commands = [
        ResolvePendingPunches::class,
        ComputeTimecards::class,
    ];

    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function boot(): void
    {
        parent::boot();

        $this->app['router']->aliasMiddleware('device.auth', AuthenticateDevice::class);

        Livewire::component('attendance.devices-index', DevicesIndex::class);
        Livewire::component('attendance.unresolved-punches', UnresolvedPunches::class);
        Livewire::component('attendance.employee-punches', EmployeePunches::class);
        Livewire::component('attendance.shifts-index', ShiftsIndex::class);
        Livewire::component('attendance.time-profiles-bulk', TimeProfilesBulk::class);
        Livewire::component('attendance.tabs.time-profile', TimeProfile::class);
    }
}
