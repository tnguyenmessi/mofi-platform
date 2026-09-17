<?php

use App\Http\Controllers\PortfolioSummaryController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::get('/api/v1/portfolios/{portfolio}/summary', PortfolioSummaryController::class)
    ->middleware(['auth', 'throttle:60,1'])
    ->name('api.v1.portfolios.summary');

Route::get('/api/v1/portfolios/{portfolio}/transactions', [TransactionController::class, 'index'])
    ->middleware(['auth', 'throttle:60,1'])->name('api.v1.transactions.index');
Route::post('/api/v1/portfolios/{portfolio}/transactions', [TransactionController::class, 'store'])
    ->middleware(['auth', 'throttle:30,1'])->name('api.v1.transactions.store');

Route::get('/', fn () => view('mofi', ['mode' => 'landing']))->name('home');
Route::get('/dashboard', DashboardController::class)->middleware('auth')->name('dashboard');
Route::get('/login', [AuthController::class, 'create'])->name('login');
Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:5,1')->name('login.store');
Route::post('/register', [AuthController::class, 'register'])->name('register');
Route::post('/logout', [AuthController::class, 'destroy'])->middleware('auth')->name('logout');
