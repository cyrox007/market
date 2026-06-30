<?php

namespace App\Models\Shipping;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Vanilo\Shipment\Models\Carrier as VaniloCarrier;

class Carrier extends VaniloCarrier
{
    use HasFactory;

    protected static function newFactory()
    {
        return \Database\Factories\Shipping\CarrierFactory::new();
    }
}
