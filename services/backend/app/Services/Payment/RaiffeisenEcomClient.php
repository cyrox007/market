<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Contracts\Gateway\GatewayLoggerInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Клиент для Raiffeisen e-commerce API (pay.raif.ru).
 * API: https://pay.raif.ru/doc/ecom.html
 * Test: https://pay-test.raif.ru
 */
class RaiffeisenEcomClient
{
    private const GATEWAY_ID = 'raiffeisen_ecom';
    private string $baseUrl;

    public function __construct(
        private readonly string $publicId,
        private readonly string $secretKey,
        bool $isTest = true
    ) {
        $this->baseUrl = $isTest ? 'https://pay-test.raif.ru' : 'https://pay.raif.ru';
    }

    /**
     * Создание заказа с получением платежной ссылки.
     * POST /api/v1/merchants/{publicId}/orders
     */
    public function createOrder(
        string $orderId,
        float $amount,
        string $comment,
        string $successUrl,
        string $failUrl
    ): array {
        $url = $this->baseUrl . '/api/v1/merchants/' . $this->publicId . '/orders';

        $body = [
            'id' => $orderId,
            'amount' => round($amount, 2),
            'comment' => $this->sanitizeComment($comment),
            'returnUrls' => [
                'successUrl' => $successUrl,
                'failUrl' => $failUrl,
            ],
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->secretKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'User-Agent' => config('app.name', 'Svetofor') . '/1.0 RaifEcom',
        ])->timeout(30)->post($url, $body);

        $data = $response->json();

        if (!$response->successful()) {
            Log::warning('RaiffeisenEcom createOrder failed', [
                'status' => $response->status(),
                'body' => $body,
                'response' => $data,
            ]);
            $this->log('order_create_failed', 'Ошибка создания заказа в Raif API: ' . ($data['message'] ?? $response->status()), [
                'order_number' => $orderId,
                'amount' => $amount,
                'http_status' => $response->status(),
            ], 'error');
            throw new \RuntimeException(
                $data['message'] ?? $data['error'] ?? 'Raiffeisen API error: ' . $response->status()
            );
        }

        $this->log('order_created', 'Заказ создан в Raif API, получена payformUrl', [
            'order_number' => $orderId,
            'amount' => $amount,
        ], 'info');

        return $data;
    }

    /**
     * Получение информации о статусе транзакции.
     * GET /api/v1/merchants/{publicId}/orders/{orderId}/transaction
     *
     * При 404 возвращает ['_not_found' => true, 'message' => '...'] — заказ не найден в Raif
     * (истёк, уже отменён или создан через GET /pay без orderId).
     */
    public function getOrderTransaction(string $orderId): array
    {
        $url = $this->baseUrl . '/api/v1/merchants/' . $this->publicId . '/orders/' . $orderId . '/transaction';

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->secretKey,
            'Accept' => 'application/json',
            'User-Agent' => config('app.name', 'Svetofor') . '/1.0 RaifEcom',
        ])->timeout(15)->get($url);

        $data = $response->json();

        if ($response->successful()) {
            return $data ?: [];
        }

        $message = $data['message'] ?? $data['error'] ?? 'Raiffeisen API error: ' . $response->status();

        if ($response->status() === 404) {
            $notFoundMessage = 'Заказ не найден в Raif API (истёк, уже отменён или создан иначе)';
            $this->log('transaction_404', $notFoundMessage, [
                'order_number' => $orderId,
                'url' => $url,
                'response' => $data,
            ], 'warning');
            return [
                '_not_found' => true,
                'message' => $notFoundMessage,
            ];
        }

        $this->log('transaction_fetch_failed', 'Ошибка получения статуса транзакции: ' . $message, [
            'order_number' => $orderId,
            'http_status' => $response->status(),
        ], 'warning');
        throw new \RuntimeException($message);
    }

    /**
     * Оформление возврата по платежу.
     * POST /api/v1/merchants/{publicId}/orders/{orderId}/refunds/{refundId}
     *
     * @param string $orderId Номер заказа (order.number)
     * @param string $refundId Уникальный идентификатор возврата (например RF-{orderId}-{uniqid})
     * @param float $amount Сумма возврата в рублях
     */
    public function createRefund(string $orderId, string $refundId, float $amount): array
    {
        $url = $this->baseUrl . '/api/v1/merchants/' . $this->publicId . '/orders/' . $orderId . '/refunds/' . $refundId;

        $body = ['amount' => round($amount, 2)];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->secretKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'User-Agent' => config('app.name', 'Svetofor') . '/1.0 RaifEcom',
        ])->timeout(30)->post($url, $body);

        $data = $response->json();

        if (!$response->successful()) {
            Log::warning('RaiffeisenEcom createRefund failed', [
                'status' => $response->status(),
                'orderId' => $orderId,
                'refundId' => $refundId,
                'response' => $data,
            ]);
            $this->log('refund_failed', 'Ошибка возврата в Raif API: ' . ($data['message'] ?? $response->status()), [
                'order_number' => $orderId,
                'refund_id' => $refundId,
                'amount' => $amount,
                'http_status' => $response->status(),
            ], 'error');
            throw new \RuntimeException(
                $data['message'] ?? $data['error'] ?? 'Raiffeisen API error: ' . $response->status()
            );
        }

        $this->log('refund_created', 'Возврат создан в Raif API', [
            'order_number' => $orderId,
            'refund_id' => $refundId,
            'amount' => $amount,
        ], 'info');

        return $data ?: [];
    }

    /**
     * Получение статуса возврата.
     * GET /api/v1/merchants/{publicId}/orders/{orderId}/refunds/{refundId}
     */
    public function getRefundStatus(string $orderId, string $refundId): array
    {
        $url = $this->baseUrl . '/api/v1/merchants/' . $this->publicId . '/orders/' . $orderId . '/refunds/' . $refundId;

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->secretKey,
            'Accept' => 'application/json',
            'User-Agent' => config('app.name', 'Svetofor') . '/1.0 RaifEcom',
        ])->timeout(15)->get($url);

        $data = $response->json();

        if (!$response->successful()) {
            throw new \RuntimeException(
                $data['message'] ?? $data['error'] ?? 'Raiffeisen API error: ' . $response->status()
            );
        }

        return $data ?: [];
    }

    /**
     * Получение информации о заказе.
     * GET /api/v1/merchants/{publicId}/orders/{orderId}
     */
    public function getOrder(string $orderId): array
    {
        $url = $this->baseUrl . '/api/v1/merchants/' . $this->publicId . '/orders/' . $orderId;

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->secretKey,
            'Accept' => 'application/json',
            'User-Agent' => config('app.name', 'Svetofor') . '/1.0 RaifEcom',
        ])->timeout(15)->get($url);

        $data = $response->json();

        if (!$response->successful()) {
            throw new \RuntimeException(
                $data['message'] ?? $data['error'] ?? 'Raiffeisen API error: ' . $response->status()
            );
        }

        return $data ?: [];
    }

    /**
     * Отмена выставленного заказа (если не оплачен).
     * DELETE /api/v1/merchants/{publicId}/orders/{orderId}
     *
     * 404 — заказ не найден в Raif (истёк, уже отменён или был создан через GET /pay без orderId).
     * Трактуем как успех (идемпотентность): желаемое состояние достигнуто.
     */
    public function deleteOrder(string $orderId): bool
    {
        $url = $this->baseUrl . '/api/v1/merchants/' . $this->publicId . '/orders/' . $orderId;

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->secretKey,
            'Accept' => 'application/json',
            'User-Agent' => config('app.name', 'Svetofor') . '/1.0 RaifEcom',
        ])->timeout(15)->delete($url);

        if ($response->successful()) {
            $this->log('order_deleted', 'Заказ отменён в Raif API', ['order_number' => $orderId], 'info');
            return true;
        }

        $data = $response->json();
        $message = $data['message'] ?? $data['error'] ?? 'Raiffeisen API error: ' . $response->status();

        if ($response->status() === 404) {
            $this->log('order_delete_404', 'Заказ не найден в Raif API (истёк, уже отменён или создан иначе). Локально помечаем отменённым.', [
                'order_number' => $orderId,
                'url' => $url,
                'response' => $data,
            ], 'warning');
            return true;
        }

        Log::warning('RaiffeisenEcom deleteOrder failed', [
            'status' => $response->status(),
            'orderId' => $orderId,
            'url' => $url,
            'response' => $data,
        ]);
        $this->log('order_delete_failed', 'Ошибка отмены заказа в Raif API: ' . $message, [
            'order_number' => $orderId,
            'http_status' => $response->status(),
        ], 'error');
        throw new \RuntimeException($message);
    }

    /**
     * Проверка подписи webhook (PAYMENT).
     * Шаблон: data.amount|data.publicId|data.order.id|data.status.value|data.status.date
     */
    public function verifyPaymentSignature(string $signature, array $data): bool
    {
        if (empty($data['data'])) {
            return false;
        }

        $d = $data['data'];
        $orderId = $d['order']['id'] ?? '';
        $statusValue = $d['status']['value'] ?? '';
        $statusDate = $d['status']['date'] ?? '';
        $amount = (string) ($d['amount'] ?? '');
        $publicId = (string) ($d['publicId'] ?? '');

        $controlString = implode('|', [$amount, $publicId, $orderId, $statusValue, $statusDate]);
        $expected = hash_hmac('sha256', $controlString, $this->secretKey);

        return hash_equals($expected, $signature);
    }

    private function sanitizeComment(string $comment): string
    {
        $len = 140;
        if (mb_strlen($comment) > $len) {
            return mb_substr($comment, 0, $len - 3) . '...';
        }
        return $comment ?: 'Заказ';
    }

    private function log(string $action, string $message, array $meta, string $level): void
    {
        $logger = app(GatewayLoggerInterface::class);
        $logger->log(self::GATEWAY_ID, $action, $message, $meta, null, 'payment', $level);
    }
}
