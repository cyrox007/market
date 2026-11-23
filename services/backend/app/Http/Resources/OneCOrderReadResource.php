<?php

namespace App\Http\Resources;

use App\Models\Order\OrderStatus;
use App\Services\Address\FulfillmentAddress;
use App\Services\Order\OrderItemDisplay;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Vanilo\Payment\Models\PaymentStatusProxy;

/**
 * Read-only 1C contract. All customer, prices, colors and bundle attributes
 * come from the placed order, never the mutable current ProductResource.
 */
class OneCOrderReadResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $order = $this->resource;
        $fulfillment = $order->fulfillment_snapshot ?? [];
        $directory = $fulfillment['address_directory'] ?? null;
        $checkout = $order->checkout_snapshot ?? [];
        $codeInfo = app(FulfillmentAddress::class)->integrationCodes($directory);
        $display = app(OrderItemDisplay::class);
        $paymentMethodLabels = $request->attributes->get('onec_payment_labels', []);
        $methodCode = $order->payment_method;

        $payments = $order->relationLoaded('payments') ? $order->payments : collect();
        $isPaid = $payments->contains(fn ($payment): bool =>
            $payment->getStatus()->equals(PaymentStatusProxy::PAID())
            && (float) $payment->getAmountPaid() >= (float) $order->total
        );

        $allocations = [];
        foreach ($fulfillment['warehouse_allocations'] ?? [] as $allocation) {
            $allocations[] = $this->allocation($allocation);
        }
        // The warehouse quote has a single-source layout when no split exists.
        if ($allocations === [] && ! empty($fulfillment['items'])) {
            $allocations[] = $this->allocation($fulfillment);
        }

        return [
            'id' => (int) $order->id,
            'number' => (string) $order->number,
            'status' => (string) $order->status,
            'status_label' => OrderStatus::tryFrom((string) $order->status)?->label() ?? (string) $order->status,
            'created_at' => $order->created_at?->toISOString(),
            'updated_at' => $order->updated_at?->toISOString(),
            'cooling_until' => $order->cooling_until?->toISOString(),
            'customer' => [
                'name' => $order->contact_name,
                'phone' => $order->contact_phone,
                'email' => $order->contact_email,
            ],
            'payment' => [
                'method_code' => $methodCode,
                'method_name' => $methodCode === null ? null : ($paymentMethodLabels[$methodCode] ?? $methodCode),
                'is_paid' => $isPaid,
            ],
            'amounts' => [
                'currency' => 'RUB',
                'subtotal' => $this->money($order->subtotal),
                'delivery_total' => $this->money($order->delivery_cost),
                'handling_in_delivery' => $this->money($fulfillment['handling_cost'] ?? 0),
                'assembly' => $this->money($order->assembly_cost),
                'additional_services' => $this->money($fulfillment['additional_services_cost'] ?? 0),
                'total' => $this->money($order->total),
            ],
            'delivery' => [
                'type' => $order->delivery_type,
                'date' => $order->delivery_date?->format('Y-m-d'),
                'time' => $order->delivery_time,
                'days_min' => $order->delivery_days_min,
                'days_max' => $order->delivery_days_max,
                'floor' => $order->delivery_floor,
                'requires_assembly' => (bool) $order->requires_assembly,
                'handling' => $order->deliveryHandlingType ? [
                    'code' => $order->deliveryHandlingType->code,
                    'name' => $order->deliveryHandlingType->name,
                ] : null,
                'address' => [
                    'text' => filled($order->address_snapshot)
                        ? (string) $order->address_snapshot
                        : data_get($checkout, 'address.full_address'),
                    'directory_external_id' => $directory['external_id']
                        ?? data_get($checkout, 'address.directory_external_id'),
                    'gar_guid' => $codeInfo['addressGarGuid'],
                    'kladr_code' => $codeInfo['addressKladrCode'],
                    'building_kladr_code' => $codeInfo['addressBuildingKladrCode'],
                    'kladr_level' => $codeInfo['addressKladrLevel'],
                    'kladr_hierarchy' => collect($directory['path'] ?? [])
                        ->filter(fn ($node): bool => is_array($node) && (
                            filled($node['kladrCode'] ?? null)
                            || (($node['source'] ?? null) === 'kladr' && filled($node['externalId'] ?? null))
                        ))
                        ->map(fn (array $node): array => [
                            'kind' => $node['kind'] ?? null,
                            'code' => (string) (filled($node['kladrCode'] ?? null)
                                ? $node['kladrCode'] : $node['externalId']),
                        ])->values()->all(),
                    'details' => $directory['details'] ?? data_get($checkout, 'address.details'),
                ],
            ],
            'fulfillment' => [
                'multi_warehouse' => (bool) ($fulfillment['multi_warehouse'] ?? false),
                'warehouse_external_id' => ($fulfillment['multi_warehouse'] ?? false)
                    ? null : ($fulfillment['warehouse_external_id'] ?? null),
                'warehouse_allocations' => $allocations,
            ],
            'bundle_snapshot' => $this->snapshotJson($order->bundle_snapshot),
            'additional_services' => $order->additionalServices->map(fn ($service): array => [
                'code' => $service->code,
                'name' => $service->pivot->service_name ?? $service->name,
                'price' => $this->money($service->pivot->price ?? 0),
                'price_type' => $service->pivot->price_type ?? $service->price_type,
            ])->values()->all(),
            'items' => $order->items->sortBy('id')->map(function ($item) use ($display): array {
                $saved = $display->present($item);
                return [
                    'line_id' => (int) $item->id,
                    'product_id' => (int) $item->product_id,
                    'product_external_id' => $saved['article'] ?? null,
                    'sku' => $saved['sku'] ?? null,
                    'parent_name' => $saved['parent_name'] ?? null,
                    'name' => (string) ($item->name ?: ($saved['name'] ?? '')),
                    'quantity' => (int) $item->quantity,
                    'unit_price' => $this->money($item->price),
                    'line_total' => $this->money($item->total),
                    'display_source' => $saved['source'] ?? 'snapshot',
                    'colors' => $saved['colors'] ?? [],
                    'materials' => $saved['materials'] ?? [],
                    'configuration' => $saved['configuration'] ?? [],
                    'specifications' => $saved['specifications'] ?? [],
                    'bundle' => $saved['bundle'] ?? ($item->pricing_snapshot['bundle'] ?? null),
                    'bundle_pricing' => $item->pricing_snapshot['bundle_pricing'] ?? null,
                ];
            })->values()->all(),
            'status_history' => $order->statusHistory->map(fn ($entry): array => [
                'status' => $entry->status,
                'occurred_at' => $entry->created_at?->toISOString(),
            ])->values()->all(),
        ];
    }

    private function allocation(array $value): array
    {
        return [
            'warehouse_id' => $value['warehouse_id'] ?? null,
            'warehouse_external_id' => $value['warehouse_external_id'] ?? null,
            'items' => collect($value['items'] ?? [])->map(fn (array $item): array => [
                'product_id' => isset($item['product_id']) ? (int) $item['product_id'] : null,
                'quantity' => (int) ($item['quantity'] ?? 0),
                'preorder_quantity' => (int) ($item['preorder_quantity'] ?? 0),
            ])->values()->all(),
        ];
    }

    private function snapshotJson(mixed $value): mixed
    {
        if ($value === null || is_array($value)) {
            return $value;
        }
        if (! is_string($value)) {
            return null;
        }

        $parsed = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $parsed : null;
    }

    private function money(mixed $value): string
    {
        return number_format((float) ($value ?? 0), 2, '.', '');
    }
}
