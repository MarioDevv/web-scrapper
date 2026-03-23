<?php

use App\Http\Controllers\AuditController;
use App\Http\Controllers\PageController;
use Illuminate\Support\Facades\Route;

Route::prefix('audits')->group(function () {
    Route::post('/',              [AuditController::class, 'start']);
    Route::get('{id}/status',     [AuditController::class, 'status']);
    Route::get('{id}/pages',      [AuditController::class, 'pages']);
    Route::post('{id}/pause',     [AuditController::class, 'pause']);
    Route::post('{id}/resume',    [AuditController::class, 'resume']);
    Route::post('{id}/cancel',    [AuditController::class, 'cancel']);
});

Route::get('pages/{id}', [PageController::class, 'show']);