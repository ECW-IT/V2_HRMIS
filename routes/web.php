<?php

use App\Livewire\ChangePassword;
use App\Livewire\Login;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', Login::class)->name('login');
});

Route::post('/logout', function () {
    auth()->logout();

    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect('/login');
})->middleware('auth')->name('logout');

Route::middleware(['auth', 'password.changed'])->group(function () {
    Route::view('/', 'dashboard')->name('home');
});

Route::get('/password/change', ChangePassword::class)
    ->middleware('auth')
    ->name('password.change');
