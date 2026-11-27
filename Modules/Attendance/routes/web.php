<?php

use Illuminate\Support\Facades\Route;
use Modules\Attendance\Livewire\DailyBoard;
use Modules\Attendance\Livewire\DeviceGroupsIndex;
use Modules\Attendance\Livewire\DevicesIndex;
use Modules\Attendance\Livewire\EmployeeTimecards;
use Modules\Attendance\Livewire\Exceptions;
use Modules\Attendance\Livewire\LateReview;
use Modules\Attendance\Livewire\OtApproval;
use Modules\Attendance\Livewire\PeriodClose;
use Modules\Attendance\Livewire\PeriodSummary;
use Modules\Attendance\Livewire\PunchesIndex;
use Modules\Attendance\Livewire\ShiftsIndex;
use Modules\Attendance\Livewire\TimecardsIndex;
use Modules\Attendance\Livewire\TimeProfilesBulk;
use Modules\Attendance\Livewire\UnresolvedPunches;

Route::middleware(['auth', 'password.changed', 'can:timecard.view'])->group(function () {
    Route::get('/attendance/board', DailyBoard::class)->name('attendance.board');
    Route::get('/attendance/timecards', TimecardsIndex::class)->name('attendance.timecards.index');
    Route::get('/attendance/timecards/{employment}', EmployeeTimecards::class)->name('attendance.timecards.show');
    Route::get('/attendance/late', LateReview::class)->name('attendance.late');
    Route::get('/attendance/period-summary', PeriodSummary::class)->name('attendance.period-summary');
});

Route::middleware(['auth', 'password.changed', 'can:attendance.adjust'])->group(function () {
    Route::get('/attendance/exceptions', Exceptions::class)->name('attendance.exceptions');
});

Route::middleware(['auth', 'password.changed', 'can:ot.approve'])->group(function () {
    Route::get('/attendance/ot', OtApproval::class)->name('attendance.ot');
});

Route::middleware(['auth', 'password.changed', 'can:period.manage'])->group(function () {
    Route::get('/attendance/period-close', PeriodClose::class)->name('attendance.period-close');
});

Route::middleware(['auth', 'password.changed', 'can:org.view'])->group(function () {
    Route::get('/attendance/shifts', ShiftsIndex::class)->name('attendance.shifts.index');
});

Route::middleware(['auth', 'password.changed', 'can:employee.update'])->group(function () {
    Route::get('/attendance/time-profiles/bulk', TimeProfilesBulk::class)->name('attendance.time-profiles.bulk');
});

Route::middleware(['auth', 'password.changed', 'can:attendance.punch.view'])->group(function () {
    Route::get('/attendance/punches', PunchesIndex::class)->name('attendance.punches.index');
});

Route::middleware(['auth', 'password.changed', 'can:attendance.device.manage'])->group(function () {
    Route::get('/attendance/device-groups', DeviceGroupsIndex::class)->name('attendance.device-groups.index');
    Route::get('/attendance/devices', DevicesIndex::class)->name('attendance.devices.index');
    Route::get('/attendance/unresolved', UnresolvedPunches::class)->name('attendance.unresolved.index');
});
