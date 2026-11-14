<?php

use Illuminate\Support\Facades\Route;
use Modules\Attendance\Livewire\DevicesIndex;
use Modules\Attendance\Livewire\ShiftsIndex;
use Modules\Attendance\Livewire\TimeProfilesBulk;
use Modules\Attendance\Livewire\UnresolvedPunches;

Route::middleware(['auth', 'password.changed', 'can:org.view'])->group(function () {
    Route::get('/attendance/shifts', ShiftsIndex::class)->name('attendance.shifts.index');
});

Route::middleware(['auth', 'password.changed', 'can:employee.update'])->group(function () {
    Route::get('/attendance/time-profiles/bulk', TimeProfilesBulk::class)->name('attendance.time-profiles.bulk');
});

Route::middleware(['auth', 'password.changed', 'can:attendance.device.manage'])->group(function () {
    Route::get('/attendance/devices', DevicesIndex::class)->name('attendance.devices.index');
    Route::get('/attendance/unresolved', UnresolvedPunches::class)->name('attendance.unresolved.index');
});
