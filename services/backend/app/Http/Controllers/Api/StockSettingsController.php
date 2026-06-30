<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Settings\ProductStockSettings;
use Illuminate\Http\JsonResponse;

class StockSettingsController extends Controller
{
    /**
     * Get stock settings
     */
    public function show(): JsonResponse
    {
        $settings = ProductStockSettings::getInstance();

        return response()->json([
            'stock_settings' => [
                'stock_low_max' => $settings->stock_low_max,
                'stock_medium_max' => $settings->stock_medium_max,
                'stock_high_max' => $settings->stock_high_max,
                'show_exact_above' => $settings->show_exact_above,
            ],
        ]);
    }
}
