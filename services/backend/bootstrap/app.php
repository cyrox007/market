<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
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
        // Р-4: за reverse-proxy берём реальный IP клиента из X-Forwarded-For,
        // иначе rate limiting схлопнет всех в один IP (адрес прокси) и даст ложные 429.
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_PROTO
                | Request::HEADER_X_FORWARDED_PORT,
        );

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
        // Единый JSON-формат ошибок API; CORS-заголовки навешивает HandleCors (config/cors.php).
        $exceptions->render(function (\Throwable $e, $request) {
            if ($request->is('api/*') && $request->expectsJson()) {
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

                return response()->json($payload, $status);
            }
        });
    })->create();
