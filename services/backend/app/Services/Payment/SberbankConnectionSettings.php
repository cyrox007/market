<?php

declare(strict_types=1);

namespace App\Services\Payment;

/** Shared runtime/readiness validation. Error messages contain no credentials or URLs. */
final class SberbankConnectionSettings
{
    public static function transportProblems(): array
    {
        $problems = [];
        $base = (string) config('payment.sberbank_acquiring.base_url');
        $url = parse_url($base);
        if (! is_array($url) || ($url['scheme'] ?? '') !== 'https' || empty($url['host'])
            || isset($url['user']) || isset($url['pass']) || isset($url['query']) || isset($url['fragment'])) {
            $problems[] = 'Укажите корректный HTTPS-адрес банка.';
        }
        if (! config('payment.sberbank_acquiring.verify_ssl', true) && ! app()->environment(['local', 'testing'])) {
            $problems[] = 'Проверка TLS обязательна вне local/testing.';
        }
        $ca = config('payment.sberbank_acquiring.ca_bundle');
        if (filled($ca) && (! is_string($ca) || ! is_file($ca) || ! is_readable($ca))) {
            $problems[] = 'Настроенный CA bundle недоступен.';
        }
        foreach (['register_path', 'status_path', 'pay_page_path'] as $key) {
            $path = config('payment.sberbank_acquiring.'.$key);
            if (filled($path) && (! is_string($path) || ! preg_match('~^/[A-Za-z0-9_./-]+$~D', $path) || str_starts_with($path, '//') || str_contains($path, '..'))) {
                $problems[] = 'Некорректный путь API Сбера.';
            }
        }
        return $problems;
    }

    public static function credentialsPresent(): bool
    {
        return filled(config('payment.sberbank_acquiring.username'))
            && filled(config('payment.sberbank_acquiring.password'));
    }

    public static function assertTransport(): void
    {
        if ($problems = self::transportProblems()) {
            throw new \RuntimeException(implode(' ', $problems));
        }
    }

    public static function verifyOptions(): array
    {
        self::assertTransport();
        if (! config('payment.sberbank_acquiring.verify_ssl', true)) {
            return ['verify' => false, 'allow_redirects' => false];
        }
        return ['verify' => config('payment.sberbank_acquiring.ca_bundle') ?: true, 'allow_redirects' => false];
    }

    public static function readinessProblems(): array
    {
        $problems = self::transportProblems();
        if (! self::credentialsPresent()) { $problems[] = 'Не заданы логин или пароль Сбера.'; }
        if (! config('payment.sberbank_acquiring.verify_ssl', true)) { $problems[] = 'Для пилота включите проверку TLS.'; }
        if (config('local_sber.enabled')) { $problems[] = 'Отключите локальный имитатор Сбера для банковской приёмки.'; }
        foreach (['app.frontend_url', 'app.url'] as $key) {
            $url = parse_url((string) config($key));
            if (! is_array($url) || ($url['scheme'] ?? '') !== 'https' || empty($url['host'])
                || isset($url['user']) || isset($url['query']) || isset($url['fragment'])) {
                $problems[] = 'Для пилота задайте HTTPS-адрес витрины и backend.';
                break;
            }
        }
        $callback = config('payment.sberbank_acquiring.callback_url');
        if (filled($callback)) {
            $url = parse_url((string) $callback);
            if (! is_array($url) || ($url['scheme'] ?? '') !== 'https' || empty($url['host'])
                || isset($url['user']) || isset($url['query']) || isset($url['fragment'])
                || ! str_ends_with($url['path'] ?? '', '/api/v1/payment/sberbank/callback')) {
                $problems[] = 'Некорректный HTTPS callback URL Сбера.';
            }
        }
        return $problems;
    }
}
