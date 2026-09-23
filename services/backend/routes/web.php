<?php

use App\Http\Controllers\Admin\AdminUiController;
use App\Http\Controllers\Admin\AdminWorkspaceController;
use App\Http\Controllers\Admin\OrderPrintController;
use App\Http\Controllers\Api\ApiMetricsController;
use Illuminate\Support\Facades\Route;

Route::get('api-docs/metrics', ApiMetricsController::class)
    ->name('api-docs.metrics');

/* Route::get('/', function () {
    return view('welcome');
}); */

Route::get('/admin-ui', [AdminUiController::class, 'index'])
    ->name('admin-ui');
Route::get('/admin-ui/{path}', [AdminUiController::class, 'asset'])
    ->where('path', '.*');

Route::get('/{any}', function () {
    return File::get(public_path('index.html'));
})->where('any', '^(?!api|admin_sv|filament|livewire|storage).*$');

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

    Route::prefix('api')->group(function () {
        Route::get('/session', [AdminWorkspaceController::class, 'session']);
        Route::get('/products', [AdminWorkspaceController::class, 'products']);
        Route::get('/products/{product}', [AdminWorkspaceController::class, 'product'])
            ->where('product', '[0-9]+');
        Route::put('/products/{product}', [AdminWorkspaceController::class, 'updateProduct'])
            ->where('product', '[0-9]+');
        Route::get('/attributes', [AdminWorkspaceController::class, 'attributes']);
        Route::put('/attributes/{attribute}', [AdminWorkspaceController::class, 'updateAttribute'])
            ->where('attribute', '[0-9]+');
        Route::get('/orders', [AdminWorkspaceController::class, 'orders']);
        Route::get('/stores', [AdminWorkspaceController::class, 'stores']);
        Route::get('/locations', [AdminWorkspaceController::class, 'locations']);
        Route::get('/warehouses', [AdminWorkspaceController::class, 'warehouses']);
    });
});
