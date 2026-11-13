<?php

use Illuminate\Support\Facades\Route;
use Modules\Attendance\Livewire\DevicesIndex;
use Modules\Attendance\Livewire\UnresolvedPunches;

Route::middleware(['auth', 'password.changed', 'can:attendance.device.manage'])->group(function () {
    Route::get('/attendance/devices', DevicesIndex::class)->name('attendance.devices.index');
    Route::get('/attendance/unresolved', UnresolvedPunches::class)->name('attendance.unresolved.index');
});
