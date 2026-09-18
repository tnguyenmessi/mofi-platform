<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LiveMarketController;
use App\Http\Controllers\PortfolioSummaryController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\WorkspaceController;
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
Route::post('/register', [AuthController::class, 'register'])->middleware(['guest', 'throttle:5,1'])->name('register');
Route::post('/logout', [AuthController::class, 'destroy'])->middleware('auth')->name('logout');
Route::get('/api/v1/market/live', LiveMarketController::class)->middleware('throttle:20,1')->name('api.v1.market.live');
Route::get('/admin', AdminController::class)->middleware(['auth', 'admin'])->name('admin.dashboard');

Route::get('/register', fn () => view('auth', ['register' => true]))->middleware('guest')->name('register.create');
Route::middleware('auth')->group(function () {
    foreach (['assets', 'portfolio', 'transactions', 'goals', 'market', 'watchlist', 'tasks', 'alerts', 'notifications', 'copilot', 'strategies', 'simulation', 'learn', 'community', 'settings'] as $page) {
        Route::get('/'.$page, DashboardController::class)->name($page);
    }
    Route::post('/workspace/alerts/check', [WorkspaceController::class, 'checkAlerts'])->middleware('throttle:20,1');
    Route::post('/workspace/learn', [WorkspaceController::class, 'lesson']);
    Route::post('/workspace/settings', [WorkspaceController::class, 'settings']);
    Route::post('/workspace/{section}/{id?}', [WorkspaceController::class, 'save'])->whereNumber('id')->middleware('throttle:60,1');
    Route::delete('/workspace/{section}/{id}', [WorkspaceController::class, 'destroy'])->whereNumber('id');
});
Route::get('/{publicPage}', fn (string $publicPage) => view('info', ['page' => $publicPage]))->whereIn('publicPage', ['products', 'about', 'pricing', 'policies']);
