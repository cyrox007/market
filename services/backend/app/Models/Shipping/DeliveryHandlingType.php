<?php

namespace App\Models\Shipping;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Тип обработки доставки (разгрузка, подъем и т.д.)
 *
 * По PSR стандартам: DeliveryHandlingType
 * Заменяет старый UnloadingType для более правильной терминологии
 */
class DeliveryHandlingType extends Model
{
    use HasFactory;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \Database\Factories\DeliveryHandlingTypeFactory::new();
    }

    protected $table = 'delivery_handling_types';

    protected $fillable = [
        'name',
        'slug',
        'code',
        'description',
        'requires_floor',
        'max_floor',
        'requires_elevator',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'requires_floor' => 'boolean',
            'requires_elevator' => 'boolean',
            'is_active' => 'boolean',
            'max_floor' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Получить локации доставки с данным типом обработки доставки
     */
    public function shippingLocations(): BelongsToMany
    {
        return $this->belongsToMany(
            ShippingLocation::class,
            'shipping_location_delivery_handling',
            'delivery_handling_type_id',
            'shipping_location_id'
        )->withPivot(['base_price', 'elevator_price', 'floor_prices', 'is_active', 'notes'])
            ->withTimestamps();
    }

    /**
     * Получить активные локации доставки
     */
    public function activeShippingLocations(): BelongsToMany
    {
        return $this->shippingLocations()->wherePivot('is_active', true);
    }
}

