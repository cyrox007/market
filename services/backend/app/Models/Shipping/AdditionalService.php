<?php

namespace App\Models\Shipping;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Дополнительная услуга (сборка, установка, упаковка и т.д.)
 */
class AdditionalService extends Model
{
    use HasFactory;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \Database\Factories\AdditionalServiceFactory::new();
    }

    protected $table = 'additional_services';

    protected $fillable = [
        'name',
        'slug',
        'code',
        'description',
        'icon',
        'price_type',
        'base_price',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'base_price' => 'decimal:2',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Получить локации доставки с данной услугой
     */
    public function shippingLocations(): BelongsToMany
    {
        return $this->belongsToMany(
            ShippingLocation::class,
            'shipping_location_additional_service',
            'additional_service_id',
            'shipping_location_id'
        )->withPivot(['price', 'is_active', 'sort_order'])
            ->withTimestamps();
    }

    /**
     * Получить активные локации доставки
     */
    public function activeShippingLocations(): BelongsToMany
    {
        return $this->shippingLocations()->wherePivot('is_active', true);
    }

    /**
     * Получить цену для локации (с учетом pivot или базовую)
     */
    public function getPriceForLocation(?ShippingLocation $location = null): ?float
    {
        if (!$location) {
            return $this->base_price;
        }

        $pivot = $location->additionalServices()
            ->where('additional_services.id', $this->id)
            ->wherePivot('is_active', true)
            ->first()?->pivot;

        if ($pivot && $pivot->price !== null) {
            return (float) $pivot->price;
        }

        return $this->base_price;
    }

    /**
     * Проверить, доступна ли услуга для локации
     */
    public function isAvailableForLocation(?ShippingLocation $location = null): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if (!$location) {
            return true;
        }

        // Проверяем через иерархию локаций
        $locationIds = array_reverse($location->getAncestorsIds());
        
        foreach ($locationIds as $locId) {
            $tempLocation = ShippingLocation::find($locId);
            if ($tempLocation) {
                $pivot = $tempLocation->additionalServices()
                    ->where('additional_services.id', $this->id)
                    ->wherePivot('is_active', true)
                    ->first()?->pivot;
                
                if ($pivot) {
                    return true;
                }
            }
        }
        
        return false;
    }
}
