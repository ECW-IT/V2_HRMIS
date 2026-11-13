<?php

use Illuminate\Support\Facades\Route;
use Modules\LegacyImport\Livewire\LegacySyncIndex;

Route::middleware(['auth', 'password.changed', 'can:employee.view', 'can:employee.bank.view'])->group(function () {
    Route::get('/legacy-sync', LegacySyncIndex::class)->name('legacy-sync.index');
});
