<?php

use Illuminate\Support\Facades\Route;
use Modules\Calendar\Livewire\HolidaysIndex;

Route::middleware(['auth', 'password.changed', 'can:org.view'])
    ->prefix('calendar')
    ->name('calendar.')
    ->group(function () {
        Route::get('/holidays', HolidaysIndex::class)->name('holidays.index');
    });
