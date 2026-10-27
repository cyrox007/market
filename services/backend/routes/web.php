<?php

use App\Http\Controllers\Admin\OrderPrintController;
use App\Http\Controllers\Api\ApiMetricsController;
use Illuminate\Support\Facades\Route;

Route::get('/offer/{token}', [\App\Http\Controllers\Api\PrivateOfferController::class, 'redirect'])->middleware('throttle:30,1');

// Read-only heartbeat for an open operator workspace, including idle/background tabs.
Route::get('/admin_sv/session-status', function (\Illuminate\Http\Request $request) {
    abort_unless($request->user('web')?->canAccessPanel(\Filament\Facades\Filament::getPanel('admin_sv')), 403);
    return response()->json([
        'user_id' => (string) $request->user('web')->getAuthIdentifier(),
        'csrf_token' => $request->session()->token(),
    ])->header('Cache-Control', 'no-store, private');
})->middleware('auth:web')->name('admin.session-status');

Route::get('api-docs/metrics', ApiMetricsController::class)
    ->name('api-docs.metrics');

/* Route::get('/', function () {
    return view('welcome');
}); */

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
});
