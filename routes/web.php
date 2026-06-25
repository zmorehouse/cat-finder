<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
Route::post('/check', [DashboardController::class, 'check'])->name('check');
Route::post('/notifications/toggle', [DashboardController::class, 'toggleNotifications'])->name('notifications.toggle');
