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
        Route::get('/products/{product}/editor-options', [AdminWorkspaceController::class, 'productEditorOptions'])
            ->where('product', '[0-9]+');
        Route::put('/products/{product}', [AdminWorkspaceController::class, 'updateProduct'])
            ->where('product', '[0-9]+');
        Route::put('/products/{product}/attributes', [AdminWorkspaceController::class, 'updateProductAttributes'])
            ->where('product', '[0-9]+');
        Route::put('/products/{product}/variation-attributes', [AdminWorkspaceController::class, 'updateProductVariationAttributes'])
            ->where('product', '[0-9]+');
        Route::post('/products/{product}/sync-1c', [AdminWorkspaceController::class, 'syncProductFromOneC'])
            ->where('product', '[0-9]+');
        Route::post('/products/{product}/variants', [AdminWorkspaceController::class, 'createProductVariant'])
            ->where('product', '[0-9]+');
        Route::put('/products/{product}/variants/{variant}', [AdminWorkspaceController::class, 'updateProductVariant'])
            ->where('product', '[0-9]+')
            ->where('variant', '[0-9]+');
        Route::delete('/products/{product}/variants/{variant}', [AdminWorkspaceController::class, 'deleteProductVariant'])
            ->where('product', '[0-9]+')
            ->where('variant', '[0-9]+');
        Route::post('/products/{product}/variants/{variant}/media', [AdminWorkspaceController::class, 'uploadProductVariantMedia'])
            ->where('product', '[0-9]+')
            ->where('variant', '[0-9]+');
        Route::delete('/products/{product}/variants/{variant}/media/{media}', [AdminWorkspaceController::class, 'deleteProductVariantMedia'])
            ->where('product', '[0-9]+')
            ->where('variant', '[0-9]+')
            ->where('media', '[0-9]+');
        Route::put('/products/{product}/variants/{variant}/media-order', [AdminWorkspaceController::class, 'reorderProductVariantMedia'])
            ->where('product', '[0-9]+')
            ->where('variant', '[0-9]+');
        Route::post('/products/{product}/media', [AdminWorkspaceController::class, 'uploadProductMedia'])
            ->where('product', '[0-9]+');
        Route::delete('/products/{product}/media/{media}', [AdminWorkspaceController::class, 'deleteProductMedia'])
            ->where('product', '[0-9]+')
            ->where('media', '[0-9]+');
        Route::put('/products/{product}/media-order', [AdminWorkspaceController::class, 'reorderProductMedia'])
            ->where('product', '[0-9]+');

        Route::post('/products/{product}/related-products', [AdminWorkspaceController::class, 'attachRelatedProduct'])
            ->where('product', '[0-9]+');
        Route::delete('/products/{product}/related-products/{related}', [AdminWorkspaceController::class, 'detachRelatedProduct'])
            ->where('product', '[0-9]+')
            ->where('related', '[0-9]+');

        Route::post('/products/{product}/bundle-products', [AdminWorkspaceController::class, 'attachBundleProducts'])
            ->where('product', '[0-9]+');
        Route::put('/products/{product}/bundle-products/{bundle}', [AdminWorkspaceController::class, 'updateBundleProduct'])
            ->where('product', '[0-9]+')
            ->where('bundle', '[0-9]+');
        Route::delete('/products/{product}/bundle-products/{bundle}', [AdminWorkspaceController::class, 'detachBundleProduct'])
            ->where('product', '[0-9]+')
            ->where('bundle', '[0-9]+');

        Route::post('/products/{product}/region-rules', [AdminWorkspaceController::class, 'createProductRegionRule'])
            ->where('product', '[0-9]+');
        Route::put('/products/{product}/region-rules/{rule}', [AdminWorkspaceController::class, 'updateProductRegionRule'])
            ->where('product', '[0-9]+')
            ->where('rule', '[0-9]+');
        Route::delete('/products/{product}/region-rules/{rule}', [AdminWorkspaceController::class, 'deleteProductRegionRule'])
            ->where('product', '[0-9]+')
            ->where('rule', '[0-9]+');
        Route::get('/attributes', [AdminWorkspaceController::class, 'attributes']);
        Route::post('/attributes', [AdminWorkspaceController::class, 'createAttribute']);
        Route::put('/attributes/{attribute}', [AdminWorkspaceController::class, 'updateAttribute'])
            ->where('attribute', '[0-9]+');
        Route::post('/attributes/{attribute}/values', [AdminWorkspaceController::class, 'createAttributeValue'])
            ->where('attribute', '[0-9]+');
        Route::get('/orders', [AdminWorkspaceController::class, 'orders']);
        Route::get('/stores', [AdminWorkspaceController::class, 'stores']);
        Route::get('/locations', [AdminWorkspaceController::class, 'locations']);
        Route::get('/warehouses', [AdminWorkspaceController::class, 'warehouses']);
    });
});
