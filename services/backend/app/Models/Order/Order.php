<?php

namespace App\Models\Order;

use App\Events\OrderCancelled;
use App\Events\OrderCompleted;
use App\Events\OrderStatusChanged;
use App\Models\Address\Address;
use App\Models\Shipping\DeliveryHandlingType;
use App\Models\Shipping\ShippingLocation;
use App\Models\Shipping\AdditionalService;
use App\Models\Inventory\Warehouse;
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
        'number',
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
        'shipping_location_id',
        'delivery_warehouse_id',
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
    }

    /**
     * Generate unique order number.
     */
    protected static function generateNumber(): string
    {
        $prefix = 'ORD';
        $date = date('Ymd');
        $lastOrder = static::whereDate('created_at', today())
            ->orderBy('id', 'desc')
            ->first();

        if ($lastOrder) {
            $lastNumber = (int) substr($lastOrder->number, -6);
            $newNumber = str_pad($lastNumber + 1, 6, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '000001';
        }

        return $prefix . $date . $newNumber;
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
    public function deliveryWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'delivery_warehouse_id');
    }

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
        }

        // Статистика продаж при завершении заказа
        if ($status === OrderStatus::DELIVERED) {
            event(new OrderCompleted($this));
        }
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
        return in_array($this->status, [OrderStatus::NEW->value, OrderStatus::AWAITING_PAYMENT->value], true);
    }

    /**
     * Заказ ожидает онлайн-оплату через эквайринг.
     */
    public function canPayOnline(): bool
    {
        if ($this->status !== OrderStatus::AWAITING_PAYMENT->value || !$this->payment_method) {
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
