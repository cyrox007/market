<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // CORS middleware применяется глобально для всех API запросов
        $middleware->api(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class,
            \App\Http\Middleware\MeasureApiTiming::class,
        ]);

        // Create a custom middleware group for API routes that need sessions
        // CORS должен применяться до session middleware
        $middleware->group('api-session', [
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Убеждаемся, что CORS заголовки добавляются даже при ошибках
        $exceptions->render(function (\Throwable $e, $request) {
            if ($request->is('api/*')) {
                $origin = $request->headers->get('Origin');
                $allowedOrigins = config('cors.allowed_origins', []);
                $isAllowedOrigin = $origin && (in_array($origin, $allowedOrigins) || in_array('*', $allowedOrigins));

                if ($request->getMethod() === 'OPTIONS') {
                    // Обрабатываем preflight запросы
                    return response('', 200)
                        ->header('Access-Control-Allow-Origin', $isAllowedOrigin ? $origin : ($allowedOrigins[0] ?? '*'))
                        ->header('Access-Control-Allow-Credentials', 'true')
                        ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS')
                        ->header('Access-Control-Allow-Headers', 'Content-Type, Accept, Authorization, X-Requested-With, X-XSRF-TOKEN')
                        ->header('Access-Control-Max-Age', '86400');
                }

                // Добавляем CORS заголовки к ответам с ошибками
                if ($request->expectsJson()) {
                    $status = 500;
                    $payload = [
                        'message' => $e->getMessage() ?: 'Server Error',
                    ];

                    if ($e instanceof ValidationException) {
                        $status = $e->status;
                        $payload = [
                            'message' => $e->getMessage() ?: 'The given data was invalid.',
                            'errors' => $e->errors(),
                        ];
                    } elseif ($e instanceof AuthenticationException) {
                        $status = 401;
                    } elseif ($e instanceof HttpExceptionInterface) {
                        $status = $e->getStatusCode();
                    }

                    return response()->json($payload, $status)->header('Access-Control-Allow-Origin', $isAllowedOrigin ? $origin : ($allowedOrigins[0] ?? '*'))
                        ->header('Access-Control-Allow-Credentials', 'true')
                        ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS')
                        ->header('Access-Control-Allow-Headers', 'Content-Type, Accept, Authorization, X-Requested-With, X-XSRF-TOKEN');
                }
            }
        });
    })->create();
