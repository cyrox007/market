<?php

namespace App\Models\Shipping;

use App\Models\Inventory\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseDeliveryRule extends Model
{
    protected $table = 'warehouse_shipping_location';

    protected $fillable = [
        'warehouse_id',
        'shipping_location_id',
        'delivery_price',
        'delivery_days_min',
        'delivery_days_max',
        'is_active',
        'priority',
    ];

    protected function casts(): array
    {
        return [
            'delivery_price' => 'decimal:2',
            'delivery_days_min' => 'integer',
            'delivery_days_max' => 'integer',
            'is_active' => 'boolean',
            'priority' => 'integer',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(ShippingLocation::class, 'shipping_location_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
