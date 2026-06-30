<?php

use App\Http\Controllers\Admin\OrderPrintController;
use App\Http\Controllers\Api\ApiMetricsController;
use Illuminate\Support\Facades\Route;

Route::get('api-docs/metrics', ApiMetricsController::class)
    ->name('api-docs.metrics');

Route::get('/', function () {
    return view('welcome');
});

// Маршрут для печати заказа (требует аутентификации через Filament)
Route::middleware([
    \Illuminate\Cookie\Middleware\EncryptCookies::class,
    \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
    \Illuminate\Session\Middleware\StartSession::class,
    \Filament\Http\Middleware\AuthenticateSession::class,
    \Illuminate\View\Middleware\ShareErrorsFromSession::class,
    \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
    \Filament\Http\Middleware\Authenticate::class,
])->prefix('admin_sv')->group(function () {
    Route::get('/orders/{orderId}/print', OrderPrintController::class)
        ->where('orderId', '[0-9]+')
        ->name('admin.orders.print');
});
