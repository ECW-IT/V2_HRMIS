<?php

use Illuminate\Support\Facades\Route;
use Modules\Organisation\Livewire\BreakTypesIndex;
use Modules\Organisation\Livewire\CompaniesIndex;
use Modules\Organisation\Livewire\EmployeeCategoriesIndex;
use Modules\Organisation\Livewire\GradesIndex;
use Modules\Organisation\Livewire\JobsIndex;
use Modules\Organisation\Livewire\LocationsIndex;
use Modules\Organisation\Livewire\OrgTree;
use Modules\Organisation\Livewire\StaffWorkCategoriesIndex;

Route::middleware(['auth', 'password.changed', 'can:org.view'])
    ->prefix('organisation')
    ->name('org.')
    ->group(function () {
        Route::middleware(['role:super-admin'])
            ->get('/companies', CompaniesIndex::class)
            ->name('companies.index');

        Route::get('/locations', LocationsIndex::class)->name('locations.index');
        Route::get('/units', OrgTree::class)->name('units.index');
        Route::get('/jobs', JobsIndex::class)->name('jobs.index');
        Route::get('/categories', EmployeeCategoriesIndex::class)->name('categories.index');
        Route::get('/staff-work-categories', StaffWorkCategoriesIndex::class)->name('staff-work-categories.index');
        Route::get('/break-types', BreakTypesIndex::class)->name('break-types.index');
        Route::get('/grades', GradesIndex::class)->name('grades.index');
    });
