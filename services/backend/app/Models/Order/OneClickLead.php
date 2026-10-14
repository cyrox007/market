<?php

namespace App\Models\Order;

use Illuminate\Database\Eloquent\Model;

class OneClickLead extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['product_snapshot' => 'array', 'consents' => 'array', 'quantity' => 'integer'];
    }
}
