<?php

namespace App\Http\Resources;

use App\Actions\Order\AutoCancelExpiredUnpaidOrderAction;
use App\Models\Order\OrderStatus;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Передаем region_id из заказа в request для OrderItemResource
        if ($this->region_id && !$request->has('_region_id')) {
            $request->merge(['_region_id' => $this->region_id]);
        }

        return [
            'id' => $this->id,
            'number' => $this->number,
            'status' => $this->status,
            'status_label' => $this->getStatusLabel(),
            'subtotal' => (float) $this->subtotal,
            'total' => (float) $this->total,
            'delivery_cost' => (float) $this->delivery_cost,
            'assembly_cost' => (float) $this->assembly_cost,
            'payment_method' => $this->payment_method,
            'payment_method_label' => $this->getPaymentMethodLabel(),
            'delivery_type' => $this->delivery_type,
            'delivery_date' => $this->delivery_date?->format('Y-m-d'),
            'delivery_time' => $this->delivery_time,
            'comment' => $this->comment,
            'contact_name' => $this->contact_name,
            'contact_phone' => $this->contact_phone,
            'contact_email' => $this->contact_email,
            'address' => new AddressResource($this->whenLoaded('address')),
            'shipping_location' => $this->when($this->shipping_location_id && $this->relationLoaded('shippingLocation'), [
                'id' => $this->shippingLocation?->id,
                'name' => $this->shippingLocation?->name,
                'type' => $this->shippingLocation?->type,
            ]),
            'delivery_warehouse' => $this->when($this->delivery_warehouse_id && $this->relationLoaded('deliveryWarehouse'), [
                'id' => $this->deliveryWarehouse?->id,
                'external_id' => $this->deliveryWarehouse?->external_id,
                'name' => $this->deliveryWarehouse?->name,
            ]),
            'region' => $this->when($this->region_id && $this->relationLoaded('region'), [
                'id' => $this->region?->id,
                'name' => $this->region?->name,
                'type' => $this->region?->type,
            ]),
            'shipping_method' => $this->when($this->shipping_method_id, [
                'id' => $this->shippingMethod?->id,
                'name' => $this->shippingMethod?->name,
                'carrier' => $this->shippingMethod?->carrier ? [
                    'id' => $this->shippingMethod->carrier->id,
                    'name' => $this->shippingMethod->carrier->name,
                ] : null,
                'delivery_days_min' => $this->delivery_days_min,
                'delivery_days_max' => $this->delivery_days_max,
                'base_price' => $this->delivery_base_price,
                'free_delivery_threshold' => $this->delivery_free_threshold,
            ]),
            'delivery_handling_type' => $this->when($this->delivery_handling_type_id, [
                'id' => $this->deliveryHandlingType?->id,
                'name' => $this->deliveryHandlingType?->name,
                'code' => $this->deliveryHandlingType?->code,
            ]),
            'delivery_floor' => $this->delivery_floor,
            'requires_assembly' => $this->requires_assembly ?? false,
            'additional_services' => $this->when($this->relationLoaded('additionalServices'), function () {
                return $this->additionalServices->map(function ($service) {
                    return [
                        'id' => $service->id,
                        'name' => $service->pivot->service_name ?? $service->name,
                        'code' => $service->code,
                        'icon' => $service->pivot->icon ?? $service->icon,
                        'price' => (float) ($service->pivot->price ?? 0),
                        'price_type' => $service->pivot->price_type ?? $service->price_type,
                    ];
                });
            }),
            'items' => OrderItemResource::collection($this->whenLoaded('items'))->resolve($request),
            'status_history' => OrderStatusHistoryResource::collection($this->whenLoaded('statusHistory')),
            'created_at' => $this->created_at?->toISOString(),
            'can_be_cancelled' => $this->canBeCancelled(),
            'can_pay' => $this->canPayOnline(),
            'payment_gateway' => $this->getPaymentGateway(),
            'payform_url' => $this->when($this->canPayOnline(), $this->getCachedPayformUrl()),
            'payment_deadline_at' => $this->resolvePaymentDeadlineAt(),
            'payment_seconds_left' => $this->resolvePaymentSecondsLeft(),
            'cancelled_due_to_unpaid_timeout' => $this->isCancelledDueToUnpaidTimeout(),
        ];
    }

    private function resolvePaymentDeadlineAt(): ?string
    {
        if ($this->status !== OrderStatus::AWAITING_PAYMENT->value || $this->created_at === null) {
            return null;
        }

        $ttlMinutes = (int) config('orders.unpaid_auto_cancel_minutes', 10);
        return $this->created_at->copy()->addMinutes($ttlMinutes)->toISOString();
    }

    private function resolvePaymentSecondsLeft(): ?int
    {
        $deadlineAt = $this->resolvePaymentDeadlineAt();
        if ($deadlineAt === null) {
            return null;
        }

        return max(0, Carbon::now()->diffInSeconds(Carbon::parse($deadlineAt), false));
    }

    private function isCancelledDueToUnpaidTimeout(): bool
    {
        if ($this->status !== OrderStatus::CANCELLED->value || ! $this->relationLoaded('statusHistory')) {
            return false;
        }

        $cancelComment = $this->statusHistory
            ->firstWhere('status', OrderStatus::CANCELLED->value)
            ?->comment;

        return is_string($cancelComment)
            && str_starts_with($cancelComment, AutoCancelExpiredUnpaidOrderAction::TIMEOUT_COMMENT_PREFIX);
    }

    private function getStatusLabel(): string
    {
        return match ($this->status) {
            'new' => 'Новый',
            'awaiting_payment' => 'Ожидание оплаты',
            'accepted' => 'Принят',
            'assembled' => 'Собран',
            'shipped' => 'Отправлен',
            'in_transit' => 'В пути',
            'delivered' => 'Доставлен',
            'cancelled' => 'Отменен',
            default => $this->status,
        };
    }

    private function getPaymentMethodLabel(): ?string
    {
        if (!$this->payment_method) {
            return null;
        }

        $paymentMethod = \App\Models\Payment\PaymentMethod::where('code', $this->payment_method)->first();
        return $paymentMethod ? $paymentMethod->name : $this->payment_method;
    }
}
