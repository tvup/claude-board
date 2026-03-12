<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/api/dashboard-data', [DashboardController::class, 'data'])->name('dashboard.data');
Route::get('/sessions/{session}', [DashboardController::class, 'session'])->name('dashboard.session');
Route::delete('/sessions/{session}', [DashboardController::class, 'destroySession'])->name('dashboard.session.destroy');
Route::post('/sessions/{session}/merge', [DashboardController::class, 'mergeSessions'])->name('dashboard.session.merge');
Route::get('/api/sessions/{session}/activity', [DashboardController::class, 'sessionActivity'])->name('dashboard.session.activity');
Route::delete('/reset', [DashboardController::class, 'resetAll'])->name('dashboard.reset');
