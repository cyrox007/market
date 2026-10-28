<?php

namespace App\Models\Order;

use App\Events\OrderCancelled;
use App\Events\OrderCompleted;
use App\Events\OrderStatusChanged;
use App\Models\Address\Address;
use App\Models\Shipping\DeliveryHandlingType;
use App\Models\Shipping\ShippingLocation;
use App\Models\Shipping\AdditionalService;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Gateway\GatewayLog;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Vanilo\Contracts\Payable;
use Vanilo\Shipment\Models\ShippingMethod;

class Order extends Model implements Payable
{
    protected $hidden = ['guest_token_hash'];
    use HasFactory;

    /**
     * Create a new factory instance for the model.
     * Нужен явный factory-resolver из-за namespace App\Models\Order\Order
     */
    protected static function newFactory()
    {
        return \Database\Factories\OrderFactory::new();
    }

    protected $fillable = [
        'user_id',
        'bundle_snapshot',
        'guest_checkout',
        'number',
        'cooling_until', 'integration_sent_at', 'integration_state', 'guest_token_hash',
        'status',
        'total',
        'subtotal',
        'delivery_cost',
        'assembly_cost',
        'payment_method',
        'delivery_type',
        'delivery_date',
        'delivery_time',
        'comment',
        'contact_name',
        'contact_phone',
        'contact_email',
        'address_id',
        'address_snapshot',
        'fulfillment_snapshot',
        'shipping_location_id',
        'region_id',
        'shipping_method_id',
        'delivery_handling_type_id',
        'delivery_floor',
        'requires_assembly',
        'delivery_days_min',
        'delivery_days_max',
        'delivery_base_price',
        'delivery_free_threshold',
        'payable_remote_id',
    ];

    protected function casts(): array
    {
        return [
            'checkout_snapshot' => 'array',
            'bundle_snapshot' => 'array',
            'guest_checkout' => 'boolean',
            'cooling_until' => 'datetime', 'integration_sent_at' => 'datetime',
            'fulfillment_snapshot' => 'array',
            'total' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'delivery_cost' => 'decimal:2',
            'assembly_cost' => 'decimal:2',
            'delivery_date' => 'date',
            'delivery_floor' => 'integer',
            'requires_assembly' => 'boolean',
            'delivery_days_min' => 'integer',
            'delivery_days_max' => 'integer',
            'delivery_base_price' => 'decimal:2',
            'delivery_free_threshold' => 'decimal:2',
        ];
    }

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($order) {
            if (empty($order->number)) {
                $order->number = static::generateNumber();
            }
            if (empty($order->status)) {
                $order->status = OrderStatus::NEW->value;
            }
        });
        static::saving(function (Order $order): void {
            if ($order->exists && $order->getRawOriginal('checkout_snapshot') !== null) {
                foreach (['number', 'payment_method', 'delivery_type', 'address_id', 'address_snapshot', 'checkout_snapshot', 'bundle_snapshot', 'cooling_until'] as $field) {
                    if ($order->isDirty($field)) {
                        throw \Illuminate\Validation\ValidationException::withMessages([$field => 'Данные оформленного заказа зафиксированы. Изменение адреса или исходного платежа запрещено.']);
                    }
                }
                $mutable = ['stock_state', 'reservation_version', 'global_stock_deltas'];
                $before = \Illuminate\Support\Arr::except($order->getOriginal('fulfillment_snapshot') ?? [], $mutable);
                $after = \Illuminate\Support\Arr::except($order->fulfillment_snapshot ?? [], $mutable);
                if ($before != $after) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['fulfillment_snapshot' => 'Адрес, склад и условия оформленного заказа менять нельзя.']);
                }
            }
            if ($order->cooling_until && in_array($order->status, ['new', 'awaiting_payment', 'accepted'], true)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['status' => 'Для нового заказа используйте «В обработке у менеджера», затем «Собирается».']);
            }
            if ($order->cooling_until && now()->lt($order->cooling_until)
                && in_array($order->status, ['accepted', 'assembling', 'assembled', 'shipped', 'in_transit', 'delivered'], true)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['status' => 'Период охлаждения заказа ещё не завершён.']);
            }
            if ($order->cooling_until && in_array($order->status, ['assembling', 'assembled', 'shipped', 'in_transit', 'delivered'], true)
                && in_array($order->getPaymentGateway(), ['raiffeisen_acquiring', 'raiffeisen_ecom', 'sberbank_acquiring'], true)
                && ! app(\App\Services\Order\OrderCooling::class)->isPaid($order)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['status' => 'Онлайн-оплата не подтверждена. Передача заказа в сборку недоступна.']);
            }
        });
    }

    /**
     * Generate unique order number.
     */
    protected static function generateNumber(): string
    {
        return \Illuminate\Support\Facades\DB::transaction(function (): string {
            $day = now()->format('Ymd');
            $db = \Illuminate\Support\Facades\DB::table('order_number_sequences');
            $db->insertOrIgnore(['day' => $day, 'last_number' => 0]);
            $sequence = (clone $db)->where('day', $day)->lockForUpdate()->first();
            $last = (int) $sequence->last_number;
            if ($last === 0) {
                $existing = static::where('number', 'like', 'ORD'.$day.'%')->orderByDesc('number')->value('number');
                $last = $existing ? (int) substr($existing, 11) : 0;
            }
            (clone $db)->where('day', $day)->update(['last_number' => $last + 1]);
            return 'ORD'.$day.str_pad((string) ($last + 1), 6, '0', STR_PAD_LEFT);
        }, 3);
    }

    public function sealCheckout(): void
    {
        if ($this->checkout_snapshot !== null) { return; }
        $address = $this->address;
        $snapshot = $address ? $address->only(['id', 'title', 'city', 'street', 'house', 'apartment', 'entrance', 'room', 'floor', 'intercom', 'comment', 'directory_external_id', 'gar_guid', 'kladr_code']) : null;
        if ($snapshot) { $snapshot['full_address'] = $this->address_snapshot ?: $address->full_address; }
        $this->forceFill(['checkout_snapshot' => ['address' => $snapshot]])->save();
    }

    public function cancellationRequest(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(CancellationRequest::class);
    }

    public function refundTask(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(RefundTask::class);
    }

    public function checkoutPaymentMethod(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Payment\PaymentMethod::class, 'payment_method', 'code');
    }

    /**
     * Get the user that owns the order.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the address for the order.
     */
    public function address(): BelongsTo
    {
        return $this->belongsTo(Address::class);
    }

    /**
     * Get the shipping location for the order.
     */
    public function shippingLocation(): BelongsTo
    {
        return $this->belongsTo(ShippingLocation::class);
    }

    /**
     * Get the region for the order (used for cart rules).
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(ShippingLocation::class, 'region_id');
    }

    /**
     * Get the shipping method for the order.
     */
    public function shippingMethod(): BelongsTo
    {
        return $this->belongsTo(ShippingMethod::class);
    }

    /**
     * Get the delivery handling type for the order.
     */
    public function deliveryHandlingType(): BelongsTo
    {
        return $this->belongsTo(DeliveryHandlingType::class);
    }

    /**
     * Get the items for the order.
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Get the additional services for the order.
     */
    public function additionalServices(): BelongsToMany
    {
        return $this->belongsToMany(
            AdditionalService::class,
            'order_additional_services',
            'order_id',
            'additional_service_id'
        )->withPivot(['service_name', 'price', 'price_type', 'icon'])
            ->withTimestamps();
    }

    /**
     * Get the status history for the order.
     */
    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('created_at', 'desc');
    }

    /**
     * Payments (Vanilo) for this order.
     */
    public function payments(): MorphMany
    {
        return $this->morphMany(\Vanilo\Payment\Models\PaymentProxy::modelClass(), 'payable');
    }

    /**
     * Логи шлюзов (оплата, доставка и т.д.) по этому заказу.
     */
    public function gatewayLogs(): HasMany
    {
        return $this->hasMany(GatewayLog::class)->orderByDesc('created_at');
    }

    public function getPayableId(): string
    {
        return (string) $this->getKey();
    }

    public function getPayableType(): string
    {
        return 'order';
    }

    public function getAmount(): float
    {
        return (float) $this->total;
    }

    public function getCurrency(): string
    {
        return 'RUB';
    }

    public function getBillpayer(): ?\Vanilo\Contracts\Billpayer
    {
        return null;
    }

    public function getNumber(): string
    {
        return (string) $this->number;
    }

    public function getPayableRemoteId(): ?string
    {
        return $this->payable_remote_id ?? null;
    }

    public function setPayableRemoteId(string $remoteId): void
    {
        $this->payable_remote_id = $remoteId;
        $this->save();
    }

    public static function findByPayableRemoteId(string $remoteId): ?Payable
    {
        return static::where('payable_remote_id', $remoteId)->first();
    }

    public function hasItems(): bool
    {
        return $this->items()->exists();
    }

    public function getItems(): \Traversable
    {
        return $this->items;
    }

    public function getTitle(): string
    {
        return 'Заказ № ' . $this->number;
    }

    /**
     * Change order status.
     * Автоматически вызывает Vanilo события при изменении статуса
     */
    public function changeStatus(OrderStatus $status, ?string $comment = null, ?int $userId = null): void
    {
        \Illuminate\Support\Facades\DB::transaction(function () use ($status, $comment, $userId): void {
        static::whereKey($this->id)->lockForUpdate()->firstOrFail();
        $this->refresh();
        if ($this->cooling_until && $this->status === OrderStatus::CANCELLED->value && $status !== OrderStatus::CANCELLED) {
            throw \Illuminate\Validation\ValidationException::withMessages(['status' => 'Отменённый заказ нельзя возобновить. Создайте новый заказ.']);
        }
        if ($this->cooling_until && now()->lt($this->cooling_until) && $status === OrderStatus::ACCEPTED) {
            $status = OrderStatus::MANAGER_PROCESSING;
        }
        $oldStatus = $this->status;
        $this->status = $status->value;
        $this->save();

        $this->statusHistory()->create([
            'status' => $status->value,
            'comment' => $comment,
            'user_id' => $userId,
        ]);

        if ($oldStatus === $status->value) {
            return;
        }

        $this->load(['items.product']);

        event(new OrderStatusChanged($this, $oldStatus, $status->value));

        // Возврат остатков при отмене (слушатель HandleOrderCancelled)
        if ($status === OrderStatus::CANCELLED) {
            event(new OrderCancelled($this));
            app(\App\Services\Order\OrderRefundTasks::class)->ensure($this);
        }

        // Статистика продаж при завершении заказа
        if ($status === OrderStatus::DELIVERED) {
            app(\App\Services\Shipping\WarehouseOrderStock::class)->consume($this);
            event(new OrderCompleted($this));
        }
        });
    }

    /**
     * Calculate total from items.
     */
    public function calculateTotal(): void
    {
        // Если items не загружены, загружаем их
        if (!$this->relationLoaded('items')) {
            $this->load('items');
        }

        // Загружаем дополнительные услуги если не загружены
        if (!$this->relationLoaded('additionalServices')) {
            $this->load('additionalServices');
        }

        $subtotal = (float) $this->items->sum('total');
        $this->subtotal = number_format($subtotal, 2, '.', '');

        // Суммируем стоимость дополнительных услуг
        // Услуги с типом 'custom' (по договоренности) не учитываются в total
        $additionalServicesTotal = (float) $this->additionalServices->sum(function ($service) {
            $priceType = $service->pivot->price_type ?? $service->price_type ?? 'fixed';
            // Услуги с типом 'custom' не добавляются к итогу
            if ($priceType === 'custom') {
                return 0;
            }
            return (float) ($service->pivot->price ?? 0);
        });

        $total = $subtotal
            + (float) ($this->delivery_cost ?? 0)
            + (float) ($this->assembly_cost ?? 0)
            + $additionalServicesTotal;
        $this->total = number_format($total, 2, '.', '');
        $this->save();
    }

    /**
     * Get current status enum.
     */
    public function getStatusEnumAttribute(): OrderStatus
    {
        return OrderStatus::from($this->status);
    }

    /**
     * Check if order can be cancelled.
     * Заказ можно отменить только если он в статусе "новый" (NEW).
     */
    public function canBeCancelled(): bool
    {
        if ($this->cooling_until) {
            return $this->status === OrderStatus::MANAGER_PROCESSING->value && now()->lt($this->cooling_until);
        }
        return in_array($this->status, [OrderStatus::NEW->value, OrderStatus::AWAITING_PAYMENT->value], true);
    }

    /**
     * Заказ ожидает онлайн-оплату через эквайринг.
     */
    public function canPayOnline(): bool
    {
        if (! in_array($this->status, [OrderStatus::AWAITING_PAYMENT->value, OrderStatus::MANAGER_PROCESSING->value], true) || !$this->payment_method
            || ($this->cooling_until && now()->gte($this->cooling_until))
            || app(\App\Services\Order\OrderCooling::class)->isPaid($this)) {
            return false;
        }

        $gateway = \App\Models\Payment\PaymentMethod::where('code', $this->payment_method)->value('gateway');

        return in_array((string) $gateway, ['raiffeisen_acquiring', 'raiffeisen_ecom', 'sberbank_acquiring'], true);
    }

    public function getPaymentGateway(): ?string
    {
        if (!$this->payment_method) {
            return null;
        }

        $gateway = \App\Models\Payment\PaymentMethod::where('code', $this->payment_method)->value('gateway');

        return $gateway ? (string) $gateway : null;
    }

    public function getLatestPayment(): ?\Vanilo\Payment\Contracts\Payment
    {
        $paymentModelClass = \Vanilo\Payment\Models\PaymentProxy::modelClass();

        return $paymentModelClass::where('payable_type', 'order')
            ->where('payable_id', $this->id)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Кэшированная ссылка на платёжную форму (если уже регистрировали платёж в банке).
     */
    public function getCachedPayformUrl(): ?string
    {
        $payment = $this->getLatestPayment();
        if (!$payment || !is_array($payment->data)) {
            return null;
        }

        $url = trim((string) ($payment->data['sberbank_form_url'] ?? $payment->data['payformUrl'] ?? ''));

        return $url !== '' ? $url : null;
    }

    /**
     * Get status label.
     */
    public function getStatusLabel(): string
    {
        return match ($this->status) {
            'new' => 'Новый',
            'manager_processing' => 'В обработке у менеджера',
            'assembling' => 'Собирается',
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

    /**
     * Get status as string (bypasses any enum casting).
     */
    public function getStatusAsString(): string
    {
        return (string) ($this->getRawOriginal('status') ?? $this->getOriginal('status') ?? $this->getAttributes()['status'] ?? '');
    }
}
