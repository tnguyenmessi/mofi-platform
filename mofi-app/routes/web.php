<?php

use App\Http\Controllers\PortfolioSummaryController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

Route::get('/api/v1/portfolios/{portfolio}/summary', PortfolioSummaryController::class)
    ->middleware(['auth', 'throttle:60,1'])
    ->name('api.v1.portfolios.summary');

Route::get('/api/v1/portfolios/{portfolio}/transactions', [TransactionController::class, 'index'])
    ->middleware(['auth', 'throttle:60,1'])->name('api.v1.transactions.index');
Route::post('/api/v1/portfolios/{portfolio}/transactions', [TransactionController::class, 'store'])
    ->middleware(['auth', 'throttle:30,1'])->name('api.v1.transactions.store');

Route::get('/', function () {
    return view('welcome');
});
