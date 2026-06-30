<?php

namespace App\Models\Settings;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductStockSettings extends Model
{
    use HasFactory;

    protected $table = 'product_stock_settings';

    protected $fillable = [
        'stock_low_max',
        'stock_medium_max',
        'stock_high_max',
        'show_exact_above',
        'warehouse_accounting_enabled',
        'fallback_to_first_warehouse',
    ];

    protected $casts = [
        'stock_low_max' => 'integer',
        'stock_medium_max' => 'integer',
        'stock_high_max' => 'integer',
        'show_exact_above' => 'integer',
        'warehouse_accounting_enabled' => 'boolean',
        'fallback_to_first_warehouse' => 'boolean',
    ];

    /**
     * Get the singleton instance of product stock settings.
     */
    public static function getInstance(): self
    {
        return static::firstOrCreate(
            ['id' => 1],
            [
                'stock_low_max' => 1,
                'stock_medium_max' => 5,
                'stock_high_max' => 10,
                'show_exact_above' => 10,
                'warehouse_accounting_enabled' => false,
                'fallback_to_first_warehouse' => true,
            ]
        );
    }

    /**
     * Get or create the singleton instance.
     */
    public static function instance(): self
    {
        return static::getInstance();
    }
}
