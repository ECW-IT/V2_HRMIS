<?php

use Illuminate\Support\Facades\Route;
use Modules\Payroll\Http\Controllers\PayslipController;
use Modules\Payroll\Livewire\PayComponentsIndex;

Route::middleware(['auth', 'password.changed', 'can:payroll.view'])->group(function () {
    Route::get('/payroll/components', PayComponentsIndex::class)->name('payroll.components');
});

Route::middleware(['auth', 'password.changed', 'can:payroll.export'])
    ->get('/pay-runs/{run}/employees/{employee}/payslip', PayslipController::class)
    ->name('payroll.payslips.show');
