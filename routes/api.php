<?php

use SeoSpider\Auditing\Infrastructure\Delivery\Http\AuditController;
use SeoSpider\Auditing\Infrastructure\Delivery\Http\PageController;
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