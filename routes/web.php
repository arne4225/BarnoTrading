<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PriceAlertController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RiskController;
use App\Http\Controllers\StatisticsController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\TradeController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\WatchlistController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/stocks', [StockController::class, 'index'])->name('stocks.index');
    Route::get('/stocks/{symbol}', [StockController::class, 'show'])->name('stocks.show');
    Route::get('/stocks/{symbol}/chart', [StockController::class, 'chart'])->name('stocks.chart');
    Route::get('/stocks/{symbol}/quote', [StockController::class, 'quote'])->name('stocks.quote');

    Route::get('/risk/preview', [RiskController::class, 'preview'])->name('risk.preview');

    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::post('/orders', [OrderController::class, 'store'])->name('orders.store');
    Route::delete('/orders/{order}', [OrderController::class, 'destroy'])->name('orders.destroy');

    Route::get('/journal', [TradeController::class, 'index'])->name('journal.index');
    Route::get('/journal/export', [TradeController::class, 'export'])->name('journal.export');
    Route::patch('/journal/{trade}', [TradeController::class, 'update'])->name('journal.update');
    Route::get('/statistics', [StatisticsController::class, 'index'])->name('statistics.index');

    Route::get('/transactions', [TransactionController::class, 'index'])->name('transactions.index');

    Route::get('/watchlist', [WatchlistController::class, 'index'])->name('watchlist.index');
    Route::post('/watchlist', [WatchlistController::class, 'store'])->name('watchlist.store');
    Route::post('/watchlists', [WatchlistController::class, 'storeList'])->name('watchlists.store');
    Route::delete('/watchlists/{watchlist}', [WatchlistController::class, 'destroyList'])->name('watchlists.destroy');
    Route::delete('/watchlist/{watchlistItem}', [WatchlistController::class, 'destroy'])->name('watchlist.destroy');

    Route::post('/alerts', [PriceAlertController::class, 'store'])->name('alerts.store');
    Route::delete('/alerts/{priceAlert}', [PriceAlertController::class, 'destroy'])->name('alerts.destroy');
    Route::post('/alerts/{priceAlert}/acknowledge', [PriceAlertController::class, 'acknowledge'])->name('alerts.acknowledge');

    Route::get('/account', [AccountController::class, 'edit'])->name('account.edit');
    Route::put('/account', [AccountController::class, 'update'])->name('account.update');
    Route::post('/account/reset', [AccountController::class, 'reset'])->name('account.reset');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
