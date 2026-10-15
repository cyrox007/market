<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Product\ProductManufacturer;
use Illuminate\Http\JsonResponse;

class ManufacturerValuesController extends Controller
{
    public function __invoke(ProductManufacturer $manufacturers): JsonResponse
    {
        return response()->json(['data' => $manufacturers->options()]);
    }
}
