<?php

use Illuminate\Support\Facades\Route;
use Modules\CoreHr\Http\Controllers\DocumentDownloadController;
use Modules\CoreHr\Livewire\BankBranchesIndex;
use Modules\CoreHr\Livewire\BanksIndex;
use Modules\CoreHr\Livewire\DocumentTypesIndex;
use Modules\CoreHr\Livewire\EmployeeProfile;
use Modules\CoreHr\Livewire\EmployeesIndex;
use Modules\CoreHr\Livewire\HireWizard;
use Modules\CoreHr\Livewire\ReadinessDashboard;

Route::middleware(['auth', 'can:employee.document.view'])
    ->get('/documents/{document}', DocumentDownloadController::class)
    ->name('documents.download');

Route::middleware(['auth', 'password.changed', 'can:employee.view'])
    ->get('/employees/readiness', ReadinessDashboard::class)
    ->name('employees.readiness');

Route::middleware(['auth', 'password.changed', 'can:employee.view'])
    ->get('/employees', EmployeesIndex::class)
    ->name('employees.index');

Route::middleware(['auth', 'password.changed', 'can:employee.create'])
    ->get('/employees/hire', HireWizard::class)
    ->name('employees.create');

Route::middleware(['auth', 'password.changed', 'can:employee.view'])
    ->get('/employees/{employment}', EmployeeProfile::class)
    ->whereNumber('employment')
    ->name('employees.show');

Route::middleware(['auth', 'password.changed', 'can:settings.view'])
    ->prefix('settings')
    ->name('settings.')
    ->group(function () {
        Route::get('/banks', BanksIndex::class)->name('banks.index');
        Route::get('/bank-branches', BankBranchesIndex::class)->name('bank-branches.index');
        Route::get('/document-types', DocumentTypesIndex::class)->name('document-types.index');
    });
