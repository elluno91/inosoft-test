<?php

use App\Http\Controllers\Controller;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;


Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
Route::put('/', [DashboardController::class, 'store'])->name('dashboard.store');
