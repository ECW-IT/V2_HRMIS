<?php

use Illuminate\Support\Facades\Route;
use Modules\Calendar\Livewire\ExpectedDaysGrid;
use Modules\Calendar\Livewire\HolidaysIndex;
use Modules\Calendar\Livewire\PayPeriodsIndex;

Route::middleware(['auth', 'password.changed', 'can:org.view'])
    ->prefix('calendar')
    ->name('calendar.')
    ->group(function () {
        Route::get('/holidays', HolidaysIndex::class)->name('holidays.index');
        Route::get('/periods', PayPeriodsIndex::class)->name('periods.index');
        Route::get('/expected-days', ExpectedDaysGrid::class)->name('expected-days.index');
    });
