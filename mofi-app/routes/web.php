<?php

use App\Http\Controllers\PortfolioSummaryController;
use Illuminate\Support\Facades\Route;

Route::get('/api/v1/portfolios/{portfolio}/summary', PortfolioSummaryController::class)
    ->middleware(['auth', 'throttle:60,1'])
    ->name('api.v1.portfolios.summary');

Route::get('/', function () {
    return view('welcome');
});
