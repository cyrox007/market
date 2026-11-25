<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Contracts\Gateway\GatewayLoggerInterface;
use App\Models\Order\Order;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SberbankAcquiringClient
{
    public function __construct(
        private readonly GatewayLoggerInterface $gatewayLog,
    ) {
    }

    /**
     * Регистрирует платёж в Сбере и возвращает URL формы оплаты (formUrl).
     */
    public function registerOrder(
        Order $order,
        float $amountRub,
        string $returnUrl,
        string $failUrl,
        ?string $orderNumberOverride = null,
        ?string $idempotencyKey = null,
    ): array {
        if (config('local_sber.enabled')) {
            return app(LocalSberSimulator::class)->register($order, $amountRub, $orderNumberOverride ?? (string) $order->number);
        }
        if (SberbankConnectionSettings::transportProblems() !== []) {
            return ['ok' => false, 'error' => 'invalid_configuration', 'registration_rejected' => true];
        }
        if (! SberbankConnectionSettings::credentialsPresent()) {
            return ['ok' => false, 'error' => 'invalid_configuration', 'registration_rejected' => true];
        }
        $baseUrl = rtrim((string) config('payment.sberbank_acquiring.base_url'), '/');
        $userName = (string) config('payment.sberbank_acquiring.username');
        $password = (string) config('payment.sberbank_acquiring.password');
        $verifySsl = (bool) config('payment.sberbank_acquiring.verify_ssl', true);

        $amountKopecks = (int) round($amountRub * 100);
        $orderNumber = $orderNumberOverride ?? (string) $order->number;
        if (! is_string($idempotencyKey) || ! Str::isUuid($idempotencyKey)) {
            return ['ok' => false, 'error' => 'missing_registration_intent_key', 'registration_rejected' => true];
        }
        $requestId = $idempotencyKey;

        $registerPath = (string) config('payment.sberbank_acquiring.register_path', '/ecomm/gw/partner/api/v1/register.do');
        $endpoint = $baseUrl . $registerPath;

        $payload = [
            'userName' => $userName,
            'password' => $password,
            'orderNumber' => $orderNumber,
            'amount' => $amountKopecks,
            'returnUrl' => $returnUrl,
            'failUrl' => $failUrl,
        ];

        $callbackUrl = trim((string) (config('payment.sberbank_acquiring.callback_url') ?? ''));
        if ($callbackUrl !== '') {
            $payload['dynamicCallbackUrl'] = $callbackUrl;
        }

        $this->gatewayLog->log('sberbank_acquiring', 'order_register_initiated', 'Инициация регистрации платежа в Сбербанке (ecom API)', [
            'order_id' => $order->id,
            'order_number' => $orderNumber,
            'amount' => $amountRub,
            'request_id' => $requestId,
            'endpoint' => $endpoint,
        ], $order, 'payment', 'info');

        /** @var Response $response */
        $response = Http::asJson()
            ->timeout(30)
            ->withOptions($this->httpVerifyOptions($verifySsl))
            ->withHeaders([
                'x-idempotencyKey' => $requestId,
            ])
            ->post($endpoint, $payload);

        return $this->parseRegisterResponse($response, $order, $orderNumber, $amountRub, $requestId, $baseUrl);
    }

    private function parseRegisterResponse(
        Response $response,
        Order $order,
        string $orderNumber,
        float $amountRub,
        string $requestId,
        string $baseUrl,
    ): array {
        $metaBase = [
            'order_id' => $order->id,
            'order_number' => $orderNumber,
            'amount' => $amountRub,
            'request_id' => $requestId,
            'http_status' => $response->status(),
        ];

        if (!$response->successful()) {
            $this->gatewayLog->log('sberbank_acquiring', 'order_register_failed', 'Сбербанк вернул ошибку HTTP при регистрации платежа', [
                ...$metaBase,
                'response_body' => mb_substr((string) $response->body(), 0, 500),
            ], $order, 'payment', 'error');

            return [
                'ok' => false,
                'error' => 'http_error',
                'registration_rejected' => in_array($response->status(), [401, 403], true),
                'http_status' => $response->status(),
                'message' => mb_substr((string) $response->body(), 0, 200),
            ];
        }

        $json = $response->json();
        if (!is_array($json)) {
            $this->gatewayLog->log('sberbank_acquiring', 'order_register_failed', 'Сбербанк вернул не-JSON ответ при регистрации платежа', $metaBase, $order, 'payment', 'error');

            return [
                'ok' => false,
                'error' => 'invalid_response',
            ];
        }

        $errorCode = (string) ($json['errorCode'] ?? '');
        $errorMessage = (string) ($json['errorMessage'] ?? '');
        $bankOrderId = isset($json['orderId']) ? (string) $json['orderId'] : null;
        $formUrl = isset($json['formUrl']) ? trim((string) $json['formUrl']) : '';

        if ($errorCode !== '' && $errorCode !== '0') {
            $this->gatewayLog->log('sberbank_acquiring', 'order_register_failed', 'Сбербанк отклонил регистрацию платежа: ' . ($errorMessage ?: ('errorCode=' . $errorCode)), [
                ...$metaBase,
                'error_code' => $errorCode,
            ], $order, 'payment', 'warning');

            return [
                'ok' => false,
                'error' => 'bank_error',
                'error_code' => $errorCode,
                'message' => $errorMessage ?: null,
            ];
        }

        if ($formUrl === '' && $bankOrderId) {
            $payPagePath = (string) config('payment.sberbank_acquiring.pay_page_path', '/pp/pay_ru');
            $formUrl = $baseUrl . $payPagePath . '?orderId=' . rawurlencode($bankOrderId);
        }

        if (!$bankOrderId || $formUrl === '') {
            $this->gatewayLog->log('sberbank_acquiring', 'order_register_failed', 'Сбербанк не вернул orderId/formUrl при регистрации платежа', [
                ...$metaBase,
                'response_keys' => array_keys($json),
            ], $order, 'payment', 'error');

            return [
                'ok' => false,
                'error' => 'missing_fields',
            ];
        }

        $this->gatewayLog->log('sberbank_acquiring', 'order_register_success', 'Сбербанк зарегистрировал платёж и вернул formUrl', [
            ...$metaBase,
            'remote_id' => $bankOrderId,
        ], $order, 'payment', 'info');

        return [
            'ok' => true,
            'remote_id' => $bankOrderId,
            'form_url' => $formUrl,
        ];
    }

    /**
     * @return array{verify: bool|string}
     */
    /**
     * Обратная сверка статуса заказа у банка (защита от подделки callback, риск Р-1).
     * POST /ecomm/gw/partner/api/v1/getOrderStatusExtended.do
     *
     * @return array<string, mixed> Ответ банка: orderStatus (0..6), amount (копейки),
     *                              actionCode, errorCode и др. Пустой массив — запрос не удался.
     */
    public function getOrderStatusExtended(string $orderId = '', string $orderNumber = ''): array
    {
        if (config('local_sber.enabled')) {
            return app(LocalSberSimulator::class)->status($orderId, $orderNumber);
        }
        // Never send a local simulator transaction to a real bank after changing configuration.
        if (LocalSberSimulator::isSimulatedId($orderId)) {
            return [];
        }
        $baseUrl = rtrim((string) config('payment.sberbank_acquiring.base_url'), '/');
        $path = (string) (config('payment.sberbank_acquiring.status_path')
            ?: '/ecomm/gw/partner/api/v1/getOrderStatusExtended.do');
        $endpoint = $baseUrl . $path;
        $verifySsl = (bool) config('payment.sberbank_acquiring.verify_ssl', true);

        $payload = [
            'userName' => (string) config('payment.sberbank_acquiring.username'),
            'password' => (string) config('payment.sberbank_acquiring.password'),
            'language' => 'ru',
        ];
        if ($orderId !== '') {
            $payload['orderId'] = $orderId;
        } elseif ($orderNumber !== '') {
            $payload['orderNumber'] = $orderNumber;
        }

        try {
            SberbankConnectionSettings::assertTransport();
            if (! SberbankConnectionSettings::credentialsPresent() || ($orderId === '' && $orderNumber === '')) { return []; }
            /** @var Response $response */
            $response = Http::asJson()
                ->timeout(15)
                ->withOptions($this->httpVerifyOptions($verifySsl))
                ->post($endpoint, $payload);

            if (! $response->successful()) {
                Log::warning('Sberbank getOrderStatusExtended returned HTTP failure', [
                    'order_id' => $orderId,
                    'http_status' => $response->status(),
                ]);
                return [];
            }

            $data = $response->json();
            return is_array($data) ? $data : [];
        } catch (\Throwable $e) {
            Log::warning('Sberbank getOrderStatusExtended failed: ' . $e->getMessage(), [
                'order_id' => $orderId,
                'order_number' => $orderNumber,
            ]);

            return [];
        }
    }

    /**
     * Submit a single refund attempt. The caller MUST persist a unique refund
     * intent BEFORE calling this method. Never retry a timed-out refund POST:
     * Sber documents a 24-hour idempotency header whose enforcement may vary.
     *
     * @return array{ok: bool, error?: string, error_code?: string}
     */
    public function refundOrder(Order $order, string $remoteId, float $amountRub, string $refundId): array
    {
        if (config('local_sber.enabled')) {
            return app(LocalSberSimulator::class)->refund($order, $remoteId, $amountRub, $refundId);
        }
        if (LocalSberSimulator::isSimulatedId($remoteId)) {
            return ['ok' => false, 'error' => 'simulator_disabled'];
        }
        $username = trim((string) config('payment.sberbank_acquiring.username'));
        $password = (string) config('payment.sberbank_acquiring.password');
        $base = rtrim((string) config('payment.sberbank_acquiring.base_url'), '/');
        if ($username === '' || $password === '' || $remoteId === ''
            || ! preg_match('/^[0-9a-fA-F-]{36}$/', $remoteId)
            || ! is_finite($amountRub) || $amountRub <= 0) {
            return ['ok' => false, 'error' => 'invalid_configuration_or_payment'];
        }

        $minor = (int) round($amountRub * 100);
        $path = '/ecomm/gw/partner/api/v1/refund.do';
        $payload = ['userName' => $username, 'password' => $password,
            'orderId' => $remoteId, 'amount' => $minor];
        $endpoint = $base . $path;

        try {
            SberbankConnectionSettings::assertTransport();
        } catch (\RuntimeException $error) {
            return ['ok' => false, 'error' => 'invalid_configuration_or_payment'];
        }

        $this->gatewayLog->log('sberbank_acquiring', 'refund_requested',
            'Запрос возврата Сбербанка', ['order_id' => $order->id,
                'refund_id' => $refundId, 'amount_rub' => $amountRub], $order, 'payment', 'info');
        try {
            $response = Http::asJson()->timeout(30)
                ->withOptions($this->httpVerifyOptions((bool) config('payment.sberbank_acquiring.verify_ssl', true)))
                ->withHeaders(['x-idempotencyKey' => $refundId])
                ->post($endpoint, $payload);
            $json = $response->json();
            if (! $response->successful() || ! is_array($json)) {
                // A gateway timeout/HTTP failure is ambiguous: it may have
                // processed the refund. No automatic resend.
                return ['ok' => false, 'error' => 'bank_unconfirmed'];
            }
            $code = (string) ($json['errorCode'] ?? '');
            return $code === '0'
                ? ['ok' => true]
                : ['ok' => false, 'error' => 'bank_rejected', 'error_code' => $code];
        } catch (\Throwable $error) {
            report($error);
            return ['ok' => false, 'error' => 'bank_unconfirmed'];
        }
    }

    private function httpVerifyOptions(bool $verifySsl): array
    {
        return SberbankConnectionSettings::verifyOptions();
    }
}
