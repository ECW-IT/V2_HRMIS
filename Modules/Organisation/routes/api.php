<?php

use Illuminate\Support\Facades\Route;
use Modules\Organisation\Http\Controllers\OrganisationController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('organisations', OrganisationController::class)->names('organisation');
});
