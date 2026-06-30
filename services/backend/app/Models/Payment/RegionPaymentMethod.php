<?php

namespace App\Models\Payment;

use App\Models\Shipping\ShippingLocation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Связь регионов с методами оплаты
 * 
 * Позволяет настраивать доступные методы оплаты для каждого региона
 */
class RegionPaymentMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'shipping_location_id',
        'payment_method_id',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Получить регион
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(ShippingLocation::class, 'shipping_location_id');
    }

    /**
     * Получить метод оплаты
     */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    /**
     * Scope для получения только активных связей
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope для получения связей по региону
     */
    public function scopeForRegion($query, $regionId)
    {
        return $query->where('shipping_location_id', $regionId);
    }

    /**
     * Scope для сортировки по sort_order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }
}
