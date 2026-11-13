<?php

use Illuminate\Support\Facades\Route;
use Modules\CoreHr\Http\Controllers\DocumentDownloadController;
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
