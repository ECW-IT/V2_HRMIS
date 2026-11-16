<?php

use Illuminate\Support\Facades\Route;
use Modules\Payroll\Http\Controllers\PayslipController;

Route::middleware(['auth', 'password.changed', 'can:payroll.export'])
    ->get('/pay-runs/{run}/employees/{employee}/payslip', PayslipController::class)
    ->name('payroll.payslips.show');
