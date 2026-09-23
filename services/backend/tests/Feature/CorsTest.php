<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * CORS консолидирован: единственный источник — config/cors.php + HandleCors.
 * Тест фиксирует поведение, чтобы никто снова не навесил ручные обработчики
 * (в routes/api.php или в рендере исключений) с расходящейся логикой.
 */
class CorsTest extends TestCase
{
    private const ALLOWED_ORIGIN = 'http://localhost:3000';

    public function test_preflight_returns_cors_headers_for_allowed_origin(): void
    {
        $response = $this->call('OPTIONS', '/api/v1/regions', [], [], [], [
            'HTTP_ORIGIN' => self::ALLOWED_ORIGIN,
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'authorization,content-type',
        ]);

        $response->assertNoContent(204);
        $response->assertHeader('Access-Control-Allow-Origin', self::ALLOWED_ORIGIN);
        $response->assertHeader('Access-Control-Allow-Credentials', 'true');
        // Метод и заголовки отражают запрошенное (allowed_methods/headers = ['*'] в конфиге),
        // а не захардкоженный список, как было в удалённых ручных обработчиках.
        $response->assertHeader('Access-Control-Allow-Methods', 'GET');
        $response->assertHeader('Access-Control-Allow-Headers', 'authorization,content-type');
    }

    public function test_successful_response_carries_cors_headers(): void
    {
        $response = $this->getJson('/api/v1/regions', ['Origin' => self::ALLOWED_ORIGIN]);

        $response->assertOk();
        $response->assertHeader('Access-Control-Allow-Origin', self::ALLOWED_ORIGIN);
        $response->assertHeader('Access-Control-Allow-Credentials', 'true');
    }

    public function test_error_response_carries_cors_headers(): void
    {
        // Заголовки на ответе-ошибке навешивает HandleCors, а не удалённый ручной блок.
        $response = $this->getJson('/api/v1/auth/me', ['Origin' => self::ALLOWED_ORIGIN]);

        $response->assertUnauthorized();
        $response->assertHeader('Access-Control-Allow-Origin', self::ALLOWED_ORIGIN);
    }

    public function test_disallowed_origin_gets_no_allow_origin_header(): void
    {
        $response = $this->getJson('/api/v1/regions', ['Origin' => 'http://evil.example']);

        $response->assertOk();
        $response->assertHeaderMissing('Access-Control-Allow-Origin');
    }
}
