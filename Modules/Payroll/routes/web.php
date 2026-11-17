<?php

use Illuminate\Support\Facades\Route;
use Modules\Payroll\Http\Controllers\PayslipController;
use Modules\Payroll\Livewire\AttendanceTiersIndex;
use Modules\Payroll\Livewire\CompanyPaySettingsIndex;
use Modules\Payroll\Livewire\CompensationIndex;
use Modules\Payroll\Livewire\GradePayRulesIndex;
use Modules\Payroll\Livewire\LoansIndex;
use Modules\Payroll\Livewire\LoanTypesIndex;
use Modules\Payroll\Livewire\PayComponentsIndex;
use Modules\Payroll\Livewire\PayEntriesIndex;
use Modules\Payroll\Livewire\SalaryAdvancesIndex;
use Modules\Payroll\Livewire\StatutoryRatesIndex;
use Modules\Payroll\Livewire\TaxSlabsIndex;
use Modules\Payroll\Livewire\YtdOpeningsIndex;

Route::middleware(['auth', 'password.changed', 'can:payroll.view'])->group(function () {
    Route::get('/payroll/components', PayComponentsIndex::class)->name('payroll.components');
    Route::get('/payroll/loan-types', LoanTypesIndex::class)->name('payroll.loan-types');
    Route::get('/payroll/statutory-rates', StatutoryRatesIndex::class)->name('payroll.statutory-rates');
    Route::get('/payroll/tax-slabs', TaxSlabsIndex::class)->name('payroll.tax-slabs');
    Route::get('/payroll/attendance-tiers', AttendanceTiersIndex::class)->name('payroll.attendance-tiers');
    Route::get('/payroll/grade-pay-rules', GradePayRulesIndex::class)->name('payroll.grade-pay-rules');
    Route::get('/payroll/company-pay-settings', CompanyPaySettingsIndex::class)->name('payroll.company-pay-settings');
});

Route::middleware(['auth', 'password.changed', 'can:loan.manage'])
    ->get('/payroll/loans', LoansIndex::class)
    ->name('payroll.loans');

Route::middleware(['auth', 'password.changed', 'can:advance.manage'])
    ->get('/payroll/advances', SalaryAdvancesIndex::class)
    ->name('payroll.advances');

Route::middleware(['auth', 'password.changed', 'can:pay-entry.manage'])
    ->get('/payroll/pay-entries', PayEntriesIndex::class)
    ->name('payroll.pay-entries');

Route::middleware(['auth', 'password.changed', 'can:compensation.view'])
    ->get('/payroll/compensation', CompensationIndex::class)
    ->name('payroll.compensation');

Route::middleware(['auth', 'password.changed', 'can:payroll.config'])
    ->get('/payroll/ytd-openings', YtdOpeningsIndex::class)
    ->name('payroll.ytd-openings');

Route::middleware(['auth', 'password.changed', 'can:payroll.export'])
    ->get('/pay-runs/{run}/employees/{employee}/payslip', PayslipController::class)
    ->name('payroll.payslips.show');
