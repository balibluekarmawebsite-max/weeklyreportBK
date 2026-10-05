<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ReportWeekController;
use App\Http\Controllers\SettingsController;
use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'dashboard' : 'login');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Weekly reports
    Route::get('reports', [ReportWeekController::class, 'index'])->name('reports.index');
    Route::get('reports/{reportWeek}', [ReportWeekController::class, 'show'])->name('reports.show');

    // Phase placeholders — screens are built in later phases (see docs/PLAN.md section 8).
    Route::view('imports', 'imports.index')->name('imports.index');
    Route::view('departments', 'departments.index')->name('departments.index');
    Route::view('exports', 'exports.index')->name('exports.index');
    Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
});

// Profile (Breeze)
Route::view('profile', 'profile')->middleware(['auth'])->name('profile');

// Logout (Breeze Livewire ships the logout action; expose it as a POST route).
Route::post('logout', function (Logout $logout) {
    $logout();

    return redirect('/');
})->middleware('auth')->name('logout');

require __DIR__.'/auth.php';
