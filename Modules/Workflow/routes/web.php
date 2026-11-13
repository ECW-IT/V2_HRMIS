<?php

use Illuminate\Support\Facades\Route;
use Modules\Workflow\Livewire\MyApprovals;

Route::middleware(['auth', 'password.changed', 'can:approval.act'])
    ->get('/approvals', MyApprovals::class)
    ->name('approvals.index');
