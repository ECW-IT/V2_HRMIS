<?php

use Illuminate\Support\Facades\Route;
use Modules\Attendance\Http\Controllers\Api\PunchIngestController;

Route::post('/v1/attendance/punches', PunchIngestController::class)
    ->middleware('device.auth')
    ->name('attendance.punches.ingest');
