<?php

use App\Http\Controllers\Api\VhpImportController;
use Illuminate\Support\Facades\Route;

/*
 | Stateless API routes (no session / CSRF). The VHP robot posts weekly-report
 | workbooks here, authenticated by an HMAC signature rather than a login.
 | See docs/PLAN.md sections 4 & 8.
 */

Route::post('vhp-import', VhpImportController::class)->name('api.vhp-import');
