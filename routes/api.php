<?php

use App\Http\Controllers\OtlpController;
use Illuminate\Support\Facades\Route;

Route::post('/v1/metrics', [OtlpController::class, 'ingestMetrics']);
Route::post('/v1/logs', [OtlpController::class, 'ingestLogs']);

Route::post('/api/sessions/{session}/project', [\App\Http\Controllers\DashboardController::class, 'updateProject'])->name('dashboard.session.project');
