<?php

declare(strict_types=1);

namespace App\Jobs\Concerns;

use App\Contracts\Gateway\GatewayLoggerInterface;
use Illuminate\Support\Facades\Log;

/**
 * Проверка IP источника callback Райффайзен (риск Р-1).
 * Мягкий режим по умолчанию: несоответствие логируется, обработка продолжается.
 * Жёсткий режим (RAIFFEISEN_CALLBACK_IP_ENFORCE=true): callback с чужого IP отклоняется.
 */
trait ChecksRaiffeisenCallbackIp
{
    /**
     * @return bool true — продолжать обработку; false — callback отклонён (жёсткий режим).
     */
    protected function passesCallbackIpCheck(
        ?string $requestIp,
        string $gatewayId,
        GatewayLoggerInterface $gatewayLog,
        mixed $subject = null
    ): bool {
        $allowed = (array) config('payment.raiffeisen_callback_allowed_ips', []);

        // Список не задан или IP неизвестен — проверка отключена.
        if ($allowed === [] || $requestIp === null || $requestIp === '') {
            return true;
        }

        if (in_array($requestIp, $allowed, true)) {
            return true;
        }

        $enforce = (bool) config('payment.raiffeisen_callback_ip_enforce', false);

        Log::warning('Raiffeisen callback: IP источника вне allowlist', [
            'gateway' => $gatewayId,
            'ip' => $requestIp,
            'allowed' => $allowed,
            'enforce' => $enforce,
        ]);

        $gatewayLog->log(
            $gatewayId,
            'callback_ip_mismatch',
            'IP источника callback вне allowlist: ' . $requestIp
                . ($enforce ? ' — отклонено (жёсткий режим)' : ' — пропущено (мягкий режим)'),
            ['ip' => $requestIp, 'allowed' => $allowed, 'enforce' => $enforce],
            $subject,
            'payment',
            'warning'
        );

        // Мягкий режим — продолжаем (true); жёсткий — блокируем (false).
        return !$enforce;
    }
}
