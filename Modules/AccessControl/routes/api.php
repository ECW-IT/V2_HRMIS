<?php

use Illuminate\Support\Facades\Route;
use Modules\AccessControl\Http\Controllers\AccessControlController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('accesscontrols', AccessControlController::class)->names('accesscontrol');
});
