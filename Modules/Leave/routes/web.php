<?php

use Illuminate\Support\Facades\Route;
use Modules\Leave\Livewire\LeaveCalendar;
use Modules\Leave\Livewire\LeavePoliciesIndex;
use Modules\Leave\Livewire\LeaveRequests;
use Modules\Leave\Livewire\LeaveTypesIndex;

Route::middleware(['auth', 'password.changed'])->prefix('leave')->name('leave.')->group(function () {
    Route::get('types', LeaveTypesIndex::class)->middleware('can:leave.view')->name('types.index');
    Route::get('policies', LeavePoliciesIndex::class)->middleware('can:leave.view')->name('policies.index');
    Route::get('requests', LeaveRequests::class)->middleware('can:leave.view')->name('requests.index');
    Route::get('calendar', LeaveCalendar::class)->middleware('can:leave.view')->name('calendar');
});
