<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Р-4: rate limiting на чувствительных эндпоинтах.
 */
class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    private function attemptLogin(string $email): int
    {
        return $this->postJson('/api/v1/auth/login', [
            'email' => $email,
            'password' => 'wrong-password',
        ])->getStatusCode();
    }

    public function test_login_is_throttled_after_limit(): void
    {
        // auth-strict = 10/мин: первые 10 попыток проходят до контроллера (не 429).
        for ($i = 0; $i < 10; $i++) {
            $this->assertNotSame(429, $this->attemptLogin('victim@example.com'));
        }

        // 11-я попытка за минуту — блокировка.
        $this->assertSame(429, $this->attemptLogin('victim@example.com'));
    }

    public function test_login_limit_is_per_email_not_shared(): void
    {
        // Исчерпываем лимит для одного email.
        for ($i = 0; $i < 11; $i++) {
            $this->attemptLogin('victim@example.com');
        }
        $this->assertSame(429, $this->attemptLogin('victim@example.com'));

        // Другой email с того же IP не должен быть заблокирован (ключ включает email).
        $this->assertNotSame(429, $this->attemptLogin('other@example.com'));
    }
}
