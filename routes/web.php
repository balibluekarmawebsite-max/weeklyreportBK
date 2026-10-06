<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\ReportWeekController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TrendController;
use App\Livewire\Actions\Logout;
use App\Support\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'dashboard' : 'login');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('trends', [TrendController::class, 'index'])->name('trends.index');

    // Switch the active property (top-bar switcher).
    Route::post('switch-property', function (Request $request) {
        $data = $request->validate(['property_id' => ['required', 'exists:properties,id']]);
        Workspace::setProperty((int) $data['property_id']);

        return back();
    })->name('property.switch');

    // Weekly reports
    Route::get('reports', [ReportWeekController::class, 'index'])->name('reports.index');
    Route::post('reports', [ReportWeekController::class, 'store'])->name('reports.store');
    Route::get('reports/{reportWeek}', [ReportWeekController::class, 'show'])->name('reports.show');
    Route::delete('reports/{reportWeek}', [ReportWeekController::class, 'destroy'])->middleware('can:manage-settings')->name('reports.destroy');

    // Phase placeholders — screens are built in later phases (see docs/PLAN.md section 8).
    Route::view('imports', 'imports.index')->name('imports.index');
    Route::view('departments', 'departments.index')->name('departments.index');
    Route::get('exports', [ExportController::class, 'index'])->name('exports.index');
    Route::get('exports/{reportWeek}/excel', [ExportController::class, 'excel'])->name('exports.excel');
    Route::get('exports/{reportWeek}/pdf', [ExportController::class, 'pdf'])->name('exports.pdf');
    Route::get('exports/{reportWeek}/word', [ExportController::class, 'word'])->name('exports.word');
    Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::put('settings/ai', [SettingsController::class, 'updateAi'])->middleware('can:manage-settings')->name('settings.ai.update');
});

// Profile (Breeze)
Route::view('profile', 'profile')->middleware(['auth'])->name('profile');

// Logout (Breeze Livewire ships the logout action; expose it as a POST route).
Route::post('logout', function (Logout $logout) {
    $logout();

    return redirect('/');
})->middleware('auth')->name('logout');

require __DIR__.'/auth.php';
