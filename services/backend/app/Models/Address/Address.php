<?php

namespace App\Models\Address;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Address extends Model
{
    use HasFactory;

    protected $table = 'user_addresses';

    protected $fillable = [
        'user_id',
        'title',
        'city',
        'street',
        'house',
        'apartment',
        'entrance',
        'is_default',
        'shipping_location_id',
        'directory_external_id', 'gar_guid', 'kladr_code', 'address_snapshot',
        'room', 'floor', 'intercom', 'comment',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'address_snapshot' => 'array',
        ];
    }

    /**
     * Get the user that owns the address.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the shipping location for this address.
     */
    public function shippingLocation(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Shipping\ShippingLocation::class);
    }

    /**
     * Scope a query to only include default addresses.
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Get full address string.
     */
    public function getFullAddressAttribute(): string
    {
        $parts = ! empty($this->address_snapshot['label'])
            ? [$this->address_snapshot['label']]
            : array_values(array_filter([$this->city, $this->street ? "ул. {$this->street}" : null, "д. {$this->house}"]));

        if ($this->apartment) {
            $parts[] = "кв. {$this->apartment}";
        }

        if ($this->entrance) {
            $parts[] = "подъезд {$this->entrance}";
        }

        if ($this->room) {
            $parts[] = "комн. {$this->room}";
        }

        return implode(', ', $parts);
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \Database\Factories\AddressFactory::new();
    }
}
