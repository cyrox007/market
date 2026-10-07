<?php

namespace App\Models\Inventory;

use App\Models\Product\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SourceProductAvailability extends Model
{
    protected $fillable = [
        'warehouse_id', 'product_id', 'available_to_order', 'processing_days_min',
        'processing_days_max', 'source', 'external_reference',
    ];

    protected function casts(): array
    {
        return [
            'available_to_order' => 'boolean',
            'processing_days_min' => 'integer',
            'processing_days_max' => 'integer',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
