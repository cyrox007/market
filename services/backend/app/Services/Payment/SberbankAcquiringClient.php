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
    ): array {
        $baseUrl = rtrim((string) config('payment.sberbank_acquiring.base_url'), '/');
        $userName = (string) config('payment.sberbank_acquiring.username');
        $password = (string) config('payment.sberbank_acquiring.password');
        $verifySsl = (bool) config('payment.sberbank_acquiring.verify_ssl', true);
        $apiFormat = strtolower((string) config('payment.sberbank_acquiring.api_format', 'ecom'));

        $amountKopecks = (int) round($amountRub * 100);
        $orderNumber = $orderNumberOverride ?? (string) $order->number;
        $requestId = (string) Str::uuid();

        if ($apiFormat === 'legacy') {
            return $this->registerLegacy($order, $baseUrl, $userName, $password, $verifySsl, $orderNumber, $amountKopecks, $returnUrl, $failUrl, $requestId, $amountRub);
        }

        return $this->registerEcom($order, $baseUrl, $userName, $password, $verifySsl, $orderNumber, $amountKopecks, $returnUrl, $failUrl, $requestId, $amountRub);
    }

    private function registerEcom(
        Order $order,
        string $baseUrl,
        string $userName,
        string $password,
        bool $verifySsl,
        string $orderNumber,
        int $amountKopecks,
        string $returnUrl,
        string $failUrl,
        string $requestId,
        float $amountRub,
    ): array {
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
            'api_format' => 'ecom',
        ], $order, 'payment', 'info');

        /** @var Response $response */
        $response = Http::asJson()
            ->timeout(30)
            ->withOptions($this->httpVerifyOptions($verifySsl))
            ->withHeaders([
                'x-idempotencyKey' => $requestId,
            ])
            ->post($endpoint, $payload);

        return $this->parseRegisterResponse($response, $order, $orderNumber, $amountRub, $requestId, $baseUrl, true);
    }

    private function registerLegacy(
        Order $order,
        string $baseUrl,
        string $userName,
        string $password,
        bool $verifySsl,
        string $orderNumber,
        int $amountKopecks,
        string $returnUrl,
        string $failUrl,
        string $requestId,
        float $amountRub,
    ): array {
        $endpoint = $baseUrl . '/payment/rest/register.do';

        $payload = [
            'userName' => $userName,
            'password' => $password,
            'orderNumber' => $orderNumber,
            'amount' => $amountKopecks,
            'returnUrl' => $returnUrl,
            'failUrl' => $failUrl,
        ];

        $this->gatewayLog->log('sberbank_acquiring', 'order_register_initiated', 'Инициация регистрации платежа в Сбербанке (legacy REST)', [
            'order_id' => $order->id,
            'order_number' => $orderNumber,
            'amount' => $amountRub,
            'request_id' => $requestId,
            'endpoint' => $endpoint,
            'api_format' => 'legacy',
        ], $order, 'payment', 'info');

        /** @var Response $response */
        $response = Http::asForm()
            ->timeout(30)
            ->acceptJson()
            ->withOptions($this->httpVerifyOptions($verifySsl))
            ->post($endpoint, $payload);

        return $this->parseRegisterResponse($response, $order, $orderNumber, $amountRub, $requestId, $baseUrl, false);
    }

    private function parseRegisterResponse(
        Response $response,
        Order $order,
        string $orderNumber,
        float $amountRub,
        string $requestId,
        string $baseUrl,
        bool $isEcomApi,
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

        if ($formUrl === '' && $bankOrderId && $isEcomApi) {
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
        $baseUrl = rtrim((string) config('payment.sberbank_acquiring.base_url'), '/');
        $path = (string) config('payment.sberbank_acquiring.status_path', '/ecomm/gw/partner/api/v1/getOrderStatusExtended.do');
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
            /** @var Response $response */
            $response = Http::asJson()
                ->timeout(15)
                ->withOptions($this->httpVerifyOptions($verifySsl))
                ->post($endpoint, $payload);

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

    private function httpVerifyOptions(bool $verifySsl): array
    {
        if (!$verifySsl) {
            return ['verify' => false];
        }

        $caBundle = config('payment.sberbank_acquiring.ca_bundle');
        if (is_string($caBundle) && $caBundle !== '' && is_file($caBundle)) {
            return ['verify' => $caBundle];
        }

        return ['verify' => true];
    }
}
