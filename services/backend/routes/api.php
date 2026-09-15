<?php

use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ArticleController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\RoomController;
use App\Http\Controllers\Api\CompareController;
use App\Http\Controllers\Api\CountersController;
use App\Http\Controllers\Api\AboutController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\StockSettingsController;
use App\Http\Controllers\Api\InteriorIdeaController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\RegionController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\ShippingController;
use App\Http\Controllers\Api\SliderController;
use App\Http\Controllers\Api\StoreController;
use App\Http\Controllers\Api\WishlistController;
use App\Http\Controllers\Api\PaymentMethodController;
use App\Http\Controllers\Api\NewsletterController;
use App\Http\Controllers\Api\RaiffeisenCallbackController;
use App\Http\Controllers\Api\RaiffeisenEcomCallbackController;
use App\Http\Controllers\Api\SberbankCallbackController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// API v1 routes
Route::prefix('v1')->group(function () {
    // Обрабатываем preflight OPTIONS запросы для всех API путей
    Route::options('{any}', function () {
        $origin = request()->headers->get('Origin');
        $allowedOrigins = config('cors.allowed_origins', []);
        $isAllowedOrigin = $origin && in_array($origin, $allowedOrigins);

        return response('', 200)
            ->header('Access-Control-Allow-Origin', $isAllowedOrigin ? $origin : ($allowedOrigins[0] ?? '*'))
            ->header('Access-Control-Allow-Credentials', 'true')
            ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Accept, Authorization, X-Requested-With, X-XSRF-TOKEN')
            ->header('Access-Control-Max-Age', '86400');
    })->where('any', '.*');
    // Public routes - auth endpoints require session middleware for cookie-based auth
    Route::middleware(['api-session'])->prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:auth');
        Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:auth-strict');
        Route::post('/password/forgot', [AuthController::class, 'forgotPassword'])->middleware('throttle:auth-strict');
        Route::post('/password/reset', [AuthController::class, 'resetPassword'])->middleware('throttle:auth-strict');
        Route::options('{any}', function () {
            $origin = request()->headers->get('Origin');
            $allowedOrigins = config('cors.allowed_origins', []);
            $isAllowedOrigin = $origin && in_array($origin, $allowedOrigins);

            return response('', 200)
                ->header('Access-Control-Allow-Origin', $isAllowedOrigin ? $origin : ($allowedOrigins[0] ?? '*'))
                ->header('Access-Control-Allow-Credentials', 'true')
                ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Accept, Authorization, X-Requested-With, X-XSRF-TOKEN')
                ->header('Access-Control-Max-Age', '86400');
        })->where('any', '.*');
    });

    // Categories (public)
    Route::prefix('categories')->group(function () {
        Route::get('/', [CategoryController::class, 'index']);
        Route::get('/tree', [CategoryController::class, 'tree']);
        Route::get('/{slug}', [CategoryController::class, 'show']);
    });

    // Rooms (public) — вторая таксономия каталога (комнаты)
    Route::prefix('rooms')->group(function () {
        Route::get('/', [RoomController::class, 'index']);
        Route::get('/tree', [RoomController::class, 'tree']);
        Route::get('/{slug}', [RoomController::class, 'show']);
    });

    // Products (public)
    Route::prefix('products')->group(function () {
        Route::get('/', [ProductController::class, 'index']);
        Route::get('/featured', [ProductController::class, 'featured']);
        Route::get('/new', [ProductController::class, 'new']);
        Route::get('/sale', [ProductController::class, 'sale']);
        Route::get('/search', [ProductController::class, 'search']);
        Route::get('/collections/{slug}', [ProductController::class, 'collection']);
        Route::get('/{id}/bundle', [ProductController::class, 'bundle']);
        Route::get('/{id}/related', [ProductController::class, 'related']);
        Route::get('/{productId}/reviews', [ReviewController::class, 'index']);
        Route::post('/{productId}/reviews', [ReviewController::class, 'store'])->middleware('throttle:writes');
        Route::get('/{slug}', [ProductController::class, 'show']);
    });

    // Sliders (public)
    Route::prefix('sliders')->group(function () {
        Route::get('/', [SliderController::class, 'index']);
        Route::get('/{slug}', [SliderController::class, 'show']);
    });

    // Articles/News (public)
    Route::prefix('articles')->group(function () {
        Route::get('/', [ArticleController::class, 'index']);
        Route::get('/{slug}', [ArticleController::class, 'show']);
    });

    // Stores (public)
    Route::prefix('stores')->group(function () {
        Route::get('/', [StoreController::class, 'index']);
        Route::get('/cities', [StoreController::class, 'cities']);
        Route::get('/{slug}', [StoreController::class, 'show']);
    });

    // Regions (public)
    Route::prefix('regions')->group(function () {
        Route::get('/', [RegionController::class, 'list']);
        Route::get('/tree', [RegionController::class, 'tree']);
        Route::get('/detect', [RegionController::class, 'detect']);
    });

    // Contact (public)
    Route::get('/contact', [ContactController::class, 'show']);

    // Stock Settings (public)
    Route::get('/stock-settings', [StockSettingsController::class, 'show']);

    // About (public)
    Route::get('/about', [AboutController::class, 'show']);

    // Interior Ideas (public)
    Route::prefix('interior-ideas')->group(function () {
        Route::get('/', [InteriorIdeaController::class, 'index']);
    });

    // Newsletter (public)
    Route::prefix('newsletter')->group(function () {
        Route::post('/subscribe', [NewsletterController::class, 'subscribe'])->middleware('throttle:writes');
    });

    // Shipping (public)
    Route::prefix('shipping')->group(function () {
        Route::get('/locations', [ShippingController::class, 'getLocations']);
        Route::get('/locations/tree', [ShippingController::class, 'getLocationTree']);
        Route::get('/locations/{locationId}', [ShippingController::class, 'getLocationInfo']);
        Route::get('/delivery-handling-types', [ShippingController::class, 'getDeliveryHandlingTypes']);
        Route::post('/calculate', [ShippingController::class, 'calculateShipping']);
        Route::get('/carriers', [ShippingController::class, 'getCarriers']);
        Route::get('/shipping-methods', [ShippingController::class, 'getShippingMethods']);
        Route::post('/shipping-methods/calculate', [ShippingController::class, 'calculateShippingMethod']);
        Route::get('/additional-services', [ShippingController::class, 'getAdditionalServices']);
    });

    // Payment Methods (public)
    Route::prefix('payment-methods')->group(function () {
        Route::get('/', [PaymentMethodController::class, 'index']);
    });

    // Счётчики шапки одним запросом (корзина + избранное + сравнение)
    Route::middleware(['api-session'])->get('/counters', [CountersController::class, 'index']);

    // Cart (public - uses sessions)
    Route::middleware(['api-session'])->prefix('cart')->group(function () {
        Route::get('/', [CartController::class, 'index']);
        Route::post('/', [CartController::class, 'add']);
        Route::get('/count', [CartController::class, 'count']);
        Route::put('/{itemId}', [CartController::class, 'update']);
        Route::delete('/{itemId}', [CartController::class, 'remove']);
        Route::delete('/', [CartController::class, 'clear']);
    });

    // Wishlist (public - uses sessions)
    Route::middleware(['api-session'])->prefix('wishlist')->group(function () {
        Route::get('/', [WishlistController::class, 'index']);
        Route::post('/{productId}', [WishlistController::class, 'add']);
        Route::delete('/{productId}', [WishlistController::class, 'remove']);
        Route::post('/{productId}/toggle', [WishlistController::class, 'toggle']);
        Route::get('/count', [WishlistController::class, 'count']);
    });

    // Compare (public - uses sessions)
    Route::middleware(['api-session'])->prefix('compare')->group(function () {
        Route::get('/', [CompareController::class, 'index']);
        Route::post('/{productId}', [CompareController::class, 'add']);
        Route::delete('/{productId}', [CompareController::class, 'remove']);
        Route::delete('/', [CompareController::class, 'clear']);
        Route::get('/count', [CompareController::class, 'count']);
    });

    // Orders: создание и payment-config без auth
    Route::middleware(['api-session'])->prefix('orders')->group(function () {
        Route::post('/', [OrderController::class, 'store'])->middleware('throttle:orders');
        Route::get('/{id}/payment-config', [OrderController::class, 'paymentConfig'])->where('id', '[0-9]+');
    });

    // Protected routes (require authentication)
    // Используем api-session для поддержки сессий и auth:sanctum для проверки аутентификации
    Route::middleware(['api-session', 'auth:sanctum'])->group(function () {
        // Auth
        Route::prefix('auth')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/me', [AuthController::class, 'me']);
            Route::put('/profile', [AuthController::class, 'updateProfile']);
            Route::put('/password', [AuthController::class, 'changePassword']);
            Route::get('/notification-settings', [AuthController::class, 'getNotificationSettings']);
            Route::put('/notification-settings', [AuthController::class, 'updateNotificationSettings']);
        });

        // Addresses
        Route::get('/addresses', [AddressController::class, 'index']);
        Route::post('/addresses', [AddressController::class, 'store']);
        Route::get('/addresses/{address}', [AddressController::class, 'show'])->where('address', '[0-9]+');
        Route::put('/addresses/{address}', [AddressController::class, 'update'])->where('address', '[0-9]+');
        Route::delete('/addresses/{address}', [AddressController::class, 'destroy'])->where('address', '[0-9]+');
        Route::post('/addresses/{address}/set-default', [AddressController::class, 'setDefault'])->where('address', '[0-9]+');

        // Orders
        Route::prefix('orders')->group(function () {
            Route::get('/', [OrderController::class, 'index']);
            Route::get('/{id}/payment-config', [OrderController::class, 'paymentConfig'])->where('id', '[0-9]+');
            Route::get('/{id}', [OrderController::class, 'show'])->where('id', '[0-9]+');
            Route::post('/{id}/cancel', [OrderController::class, 'cancel'])->where('id', '[0-9]+');
            Route::post('/{id}/repeat', [OrderController::class, 'repeat'])->where('id', '[0-9]+');
        });

        // // Bonuses
        // Route::prefix('bonuses')->group(function () {
        //     Route::get('/balance', [BonusController::class, 'balance']);
        //     Route::get('/history', [BonusController::class, 'history']);
        // });

        // // Chats
        // Route::prefix('chats')->group(function () {
        //     Route::get('/', [ChatController::class, 'index']);
        //     Route::get('/{chat}', [ChatController::class, 'show']);
        //     Route::post('/{chat}/messages', [ChatController::class, 'sendMessage']);
        //     Route::post('/orders/{order}/create', [ChatController::class, 'createOrGet']);
        // });
    });

    // Callback от Райффайзен (без auth)
    Route::post('payment/raiffeisen/callback', RaiffeisenCallbackController::class)
        ->name('payment.raiffeisen.callback');

    // Webhook от Райффайзен e-commerce API (pay.raif.ru)
    Route::post('payment/raiffeisen-ecom/callback', RaiffeisenEcomCallbackController::class)
        ->name('payment.raiffeisen_ecom.callback');

    // Callback от Сбербанка (ecom)
    Route::post('payment/sberbank/callback', SberbankCallbackController::class)
        ->name('payment.sberbank.callback');

    if (config('app.debug')) {
        Route::get('payment/raiffeisen/config-check', function () {
            $fromConfig = config('payment.raiffeisen.public_id', '');
            if ((string) $fromConfig === '') {
                $fromConfig = env('RAIFFEISEN_PUBLIC_ID', '');
            }
            $url = config('payment.raiffeisen.url', '');
            if ((string) $url === '') {
                $url = env('RAIFFEISEN_PAYMENT_URL', 'https://pay-test.raif.ru/pay');
            }
            return response()->json([
                'gateway_client_config' => ['publicId' => (string) $fromConfig, 'url' => (string) $url],
                'publicId_set' => (string) $fromConfig !== '',
                'source' => 'config + env fallback',
            ]);
        });
    }
});
