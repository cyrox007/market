<?php

namespace App\Services\Inventory\Integrations;

use App\Models\Order\Order;
use App\Services\Inventory\Contracts\InventorySyncInterface;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Интеграция с 1С через API
 *
 * Реализует обмен заказами и остатками с системой 1С
 */
class Integration1CApiService implements InventorySyncInterface
{
    protected string $baseUrl;
    protected string $apiKey;
    protected int $timeout;

    public function __construct()
    {
        $configPrefix = (bool) config('services.integration_1c.enabled', false)
            ? 'services.integration_1c'
            : 'services.onec';

        $this->baseUrl = (string) config($configPrefix . '.base_url', '');
        $this->apiKey = (string) config($configPrefix . '.api_key', '');
        $this->timeout = (int) config($configPrefix . '.timeout', 30);
    }

    public function syncOrderCreated(Order $order): bool
    {
        try {
            $order->load(['items.product', 'address', 'user']);
            $payload = $this->formatOrderFor1C($order);

            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post("{$this->baseUrl}/api/orders", $payload);

            if ($response->successful()) {
                Log::info("Order synced to 1C", [
                    'order_id' => $order->id,
                    'order_number' => $order->number,
                ]);
                return true;
            }

            Log::warning("Failed to sync order to 1C", [
                'order_id' => $order->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error("Exception syncing order to 1C", [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function syncOrderCancelled(Order $order): bool
    {
        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                ])
                ->patch("{$this->baseUrl}/api/orders/{$order->number}/cancel");

            if ($response->successful()) {
                Log::info("Cancelled order synced to 1C", [
                    'order_id' => $order->id,
                    'order_number' => $order->number,
                ]);
                return true;
            }

            return false;
        } catch (\Exception $e) {
            Log::error("Exception syncing cancelled order to 1C", [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function syncOrderCompleted(Order $order): bool
    {
        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                ])
                ->patch("{$this->baseUrl}/api/orders/{$order->number}/complete");

            if ($response->successful()) {
                Log::info("Completed order synced to 1C", [
                    'order_id' => $order->id,
                    'order_number' => $order->number,
                ]);
                return true;
            }

            return false;
        } catch (\Exception $e) {
            Log::error("Exception syncing completed order to 1C", [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function fetchStockLevels(array $productIds): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                ])
                ->post("{$this->baseUrl}/api/products/stock", [
                    'product_ids' => $productIds,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['stocks'] ?? [];
            }

            return [];
        } catch (\Exception $e) {
            Log::error("Exception fetching stock from 1C", [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    public function fetchProductBySku(string $sku): ?array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                ])
                ->get("{$this->baseUrl}/api/products/{$sku}");

            if ($response->successful()) {
                return $response->json();
            }

            return null;
        } catch (\Exception $e) {
            Log::error("Exception fetching product from 1C", [
                'sku' => $sku,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    protected function formatOrderFor1C(Order $order): array
    {
        $items = [];
        foreach ($order->items as $item) {
            $items[] = [
                'product_id' => $item->product_id,
                'sku' => $item->product->sku ?? null,
                'name' => $item->product->name ?? null,
                'quantity' => $item->quantity,
                'price' => (float) $item->price,
                'total' => (float) $item->total,
            ];
        }

        return [
            'number' => $order->number,
            'status' => $order->status,
            'user_id' => $order->user_id,
            'contact_name' => $order->contact_name,
            'contact_phone' => $order->contact_phone,
            'contact_email' => $order->contact_email,
            'subtotal' => (float) $order->subtotal,
            'total' => (float) $order->total,
            'delivery_cost' => (float) $order->delivery_cost,
            'assembly_cost' => (float) $order->assembly_cost,
            'payment_method' => $order->payment_method,
            'delivery_type' => $order->delivery_type,
            'delivery_date' => $order->delivery_date instanceof CarbonInterface
                ? $order->delivery_date->format('Y-m-d')
                : null,
            'delivery_time' => $order->delivery_time,
            'comment' => $order->comment,
            'address' => $order->address ? [
                'city' => $order->address->city,
                'street' => $order->address->street,
                'house' => $order->address->house,
                'apartment' => $order->address->apartment,
            ] : null,
            'items' => $items,
            'created_at' => $order->created_at->toIso8601String(),
        ];
    }
}
