<?php

namespace App\Models\Inventory;

use App\Models\Page\Store;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PhysicalSite extends Model
{
    protected $fillable = ['name', 'address', 'city', 'address_external_id', 'gar_guid', 'kladr_code',
        'address_snapshot', 'latitude', 'longitude', 'coordinate_source', 'coordinate_precision'];

    protected $casts = ['address_snapshot' => 'array', 'latitude' => 'decimal:7', 'longitude' => 'decimal:7'];

    public function stores(): HasMany
    {
        return $this->hasMany(Store::class);
    }

    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class);
    }
}
