<?php

use Illuminate\Support\Facades\Route;
use Modules\AccessControl\Livewire\Audit\AuditLogList;
use Modules\AccessControl\Livewire\Roles\RoleList;
use Modules\AccessControl\Livewire\Roles\RolePermissions;
use Modules\AccessControl\Livewire\Users\UserList;

Route::middleware(['auth', 'password.changed', 'can:access-control.view'])
    ->prefix('access-control')
    ->name('access-control.')
    ->group(function () {
        Route::get('/users', UserList::class)->name('users.index');
        Route::get('/roles', RoleList::class)->name('roles.index');
        Route::get('/roles/{role}/permissions', RolePermissions::class)->name('roles.permissions');
    });

Route::middleware(['auth', 'password.changed', 'can:audit.view'])
    ->prefix('access-control')
    ->name('access-control.')
    ->group(function () {
        Route::get('/audit', AuditLogList::class)->name('audit.index');
    });
