<?php

use Illuminate\Support\Facades\Route;
use Modules\Organisation\Livewire\CompaniesIndex;
use Modules\Organisation\Livewire\GradesIndex;
use Modules\Organisation\Livewire\JobsIndex;
use Modules\Organisation\Livewire\LocationsIndex;
use Modules\Organisation\Livewire\OrgTree;

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
        Route::get('/grades', GradesIndex::class)->name('grades.index');
    });
