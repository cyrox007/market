<?php

declare(strict_types=1);

namespace App\Support\Integration;

use Illuminate\Support\Facades\Log;

/**
 * Нормализация email перед отправкой в 1С (строгая валидация на стороне API).
 */
final class OrderSyncEmailNormalizer
{
    public function normalize(?string $email, ?int $orderId = null): string
    {
        $original = trim((string) $email);
        if ($original === '') {
            return '';
        }

        $normalized = $this->applyBasicNormalization($original);
        $normalized = $this->applyTypoFixes($normalized);

        if ($this->isValidEmail($normalized)) {
            return $normalized;
        }

        Log::warning('1C order sync: email omitted after normalization', [
            'order_id' => $orderId,
            'original' => $original,
            'normalized' => $normalized,
        ]);

        return '';
    }

    private function applyBasicNormalization(string $email): string
    {
        $email = mb_strtolower(trim($email));
        $email = preg_replace('/\s+/u', '', $email) ?? $email;

        if (str_contains($email, '@')) {
            [$local, $domain] = explode('@', $email, 2);
            $local = trim($local, '.');
            $domain = str_replace(',', '.', $domain);

            return $local . '@' . $domain;
        }

        return str_replace(',', '.', $email);
    }

    /**
     * Частые опечатки в домене, из‑за которых 1С отвечает 400.
     */
    private function applyTypoFixes(string $email): string
    {
        if (preg_match('/^([^@]+)@([^@]+)\.w$/u', $email) === 1) {
            return preg_replace('/\.w$/u', '.ru', $email) ?? $email;
        }

        if (preg_match('/^([^@]+)@([^@]+)\.r$/u', $email) === 1) {
            return $email . 'u';
        }

        return $email;
    }

    private function isValidEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}
