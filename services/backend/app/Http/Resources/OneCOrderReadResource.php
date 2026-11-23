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
        $isPickup = $order->delivery_type === 'pickup';
        // Older orders may keep the address only in the sealed checkout snapshot.
        // Use those saved codes and flat details; never consult today's address book.
        if ($directory === null && ! $isPickup && is_array($checkout['address'] ?? null)) {
            $address = $checkout['address'];
            $directory = [
                'external_id' => $address['directory_external_id'] ?? null,
                'gar_guid' => $address['gar_guid'] ?? null,
                'kladr_code' => $address['kladr_code'] ?? null,
                'details' => $address['details'] ?? array_filter(
                    \Illuminate\Support\Arr::only($address, ['apartment', 'room', 'entrance', 'floor', 'intercom', 'comment']),
                    fn ($value): bool => $value !== null && $value !== ''
                ),
            ];
        }
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
            'comment' => $order->comment,
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
                // Customer address exists ONLY for courier delivery, never pickup.
                'address' => $isPickup ? null : $this->address(
                    $directory,
                    filled($order->address_snapshot)
                        ? (string) $order->address_snapshot
                        : data_get($checkout, 'address.full_address'),
                    data_get($checkout, 'address.directory_external_id'),
                    data_get($checkout, 'address.details')
                ),
                // A pickup can be directly at the warehouse or at an associated
                // store on the same physical site. Both use the checkout snapshot.
                'pickup' => $isPickup ? [
                    'warehouse_id' => isset($fulfillment['warehouse_id'])
                        ? (int) $fulfillment['warehouse_id'] : null,
                    'warehouse_external_id' => $fulfillment['warehouse_external_id'] ?? null,
                    'store_id' => isset($fulfillment['store_id'])
                        ? (int) $fulfillment['store_id'] : null,
                    'point_name' => $fulfillment['name'] ?? null,
                    'address' => $this->address(
                        $directory,
                        $this->pickupAddress($fulfillment, $order->address_snapshot)
                    ),
                    'hours' => $fulfillment['hours'] ?? null,
                    'opening_hours' => $fulfillment['opening_hours'] ?? null,
                    'timezone' => $fulfillment['timezone'] ?? null,
                ] : null,
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

    /**
     * Resolve historical delivery/pickup address, without querying the
     * current warehouse, store or customer's mutable address book.
     */
    private function address(?array $snapshot, mixed $text, mixed $externalId = null, mixed $details = null): ?array
    {
        $text = is_string($text) && trim($text) !== '' ? $text : null;
        if ($text === null && $snapshot === null && $externalId === null) {
            return null;
        }

        $codes = app(FulfillmentAddress::class)->integrationCodes($snapshot);
        $details = $snapshot['details'] ?? $details;

        return [
            'text' => $text,
            'directory_external_id' => $snapshot['external_id'] ?? $externalId,
            'gar_guid' => $codes['addressGarGuid'],
            'kladr_code' => $codes['addressKladrCode'],
            'building_kladr_code' => $codes['addressBuildingKladrCode'],
            'kladr_level' => $codes['addressKladrLevel'],
            'kladr_hierarchy' => collect($snapshot['path'] ?? [])
                ->filter(fn ($node): bool => is_array($node) && (
                    filled($node['kladrCode'] ?? null)
                    || (($node['source'] ?? null) === 'kladr' && filled($node['externalId'] ?? null))
                ))
                ->map(fn (array $node): array => [
                    'kind' => $node['kind'] ?? null,
                    'code' => (string) (filled($node['kladrCode'] ?? null)
                        ? $node['kladrCode'] : $node['externalId']),
                ])->values()->all(),
            'details' => is_array($details) && $details === [] ? null : $details,
        ];
    }

    private function pickupAddress(array $fulfillment, mixed $historicalAddress): ?string
    {
        // WarehousePickup::select() saves the physical-site/warehouse address.
        // The order-level address_snapshot is also a pickup point for pickup
        // orders and is a reliable legacy fallback.
        foreach ([
            $fulfillment['address'] ?? null,
            data_get($fulfillment, 'address_directory.label'),
            $historicalAddress,
        ] as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return $candidate;
            }
        }

        return null;
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
