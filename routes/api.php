<?php

use App\Http\Controllers\OtlpController;
use Illuminate\Support\Facades\Route;

Route::post('/v1/metrics', [OtlpController::class, 'ingestMetrics']);
Route::post('/v1/logs', [OtlpController::class, 'ingestLogs']);
