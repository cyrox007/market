<?php

// Explicit local-only smoke; fixtures are rolled back, never migrate:fresh on the app DB.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['cache.default' => 'array', 'services.integration_1c.enabled' => false, 'queue.default' => 'sync']);
$db = \Illuminate\Support\Facades\DB::connection();
if ($db->getDatabaseName() !== 'laravel' || $db->getConfig('host') !== 'mysql-8.4') {
    throw new RuntimeException('Only the approved local laravel database is permitted');
}
$check = static function (bool $condition, string $message): void {
    if (! $condition) {
        throw new RuntimeException($message);
    }
};
$db->beginTransaction();
try {
    $region = ['kind' => 'region', 'externalId' => 'smoke-region', 'name' => 'Регион'];
    $city = ['kind' => 'locality', 'externalId' => 'smoke-city', 'name' => 'Город'];
    \Illuminate\Support\Facades\Http::fake(function ($request) use ($region, $city) {
        $id = basename(parse_url($request->url(), PHP_URL_PATH));
        $target = $id === 'smoke-region' ? $region : ['kind' => 'building', 'source' => 'gar', 'externalId' => $id, 'name' => '1', 'latitude' => 52.6, 'longitude' => 39.6, 'coordinatePrecision' => 'address_point'];

        return \Illuminate\Support\Facades\Http::response(['target' => $target, 'path' => [$region, $city, $target], 'label' => 'Регион, Город, дом 1']);
    });
    $a = \App\Models\Product\Product::factory()->create(['price' => 1000, 'is_variable' => false]);
    $b = \App\Models\Product\Product::factory()->create(['price' => 999, 'is_variable' => true]);
    $variant = \App\Models\Product\Product::factory()->create(['price' => 999, 'parent_product_id' => $b->id]);
    $location = \App\Models\Shipping\ShippingLocation::factory()->create();
    \App\Models\Product\ProductRegionRule::create(['product_id' => $a->id, 'shipping_location_id' => $location->id, 'price_modifier_type' => 'percent', 'price_modifier_value' => 10, 'is_active' => true]);
    $roots = \App\Models\Product\Product::active()->whereIn('products.id', [$a->id, $b->id]);
    $filter = app(\App\Services\Product\CatalogFilterService::class)->forLocation($location);
    $meta = $filter->meta(clone $roots, []);
    $check($meta['price']['min'] === 999.0 && $meta['price']['max'] === 1100.0, 'MySQL effective-price range disagrees');
    $count = $filter->apply(clone $roots, $filter->filters(['price_max' => 1000]))->count('products.id');
    $check($count === 1, 'MySQL price predicate/count disagrees');
    $warehouse = \App\Models\Inventory\Warehouse::create(['name' => 'Smoke temporary warehouse', 'source_type' => 'physical', 'stock_mode' => 'quantity', 'pickup_enabled' => true, 'latitude' => 52.6, 'longitude' => 39.6]);
    foreach ([$a, $variant] as $sku) {
        $warehouse->productStocks()->create(['product_id' => $sku->id, 'quantity' => 5]);
    }
    $warehouse->deliveryProfiles()->create(['name' => 'Smoke profile', 'base_price' => 100, 'delivery_days_min' => 1, 'delivery_days_max' => 2, 'directory_territories' => [['region_id' => 'smoke-region']]]);
    $destination = app(\App\Services\Shipping\WarehouseCoverage::class)->destination(null, ['path' => [$region]], ['latitude' => 52.6, 'longitude' => 39.6]);
    $query = clone $roots;
    app(\App\Services\Inventory\CatalogWarehouseDistance::class)->order($query, $filter->skus(clone $roots, []), $destination);
    $rows = $query->get();
    $check($rows->count() === 2 && $rows->every(fn ($row) => $row->nearest_distance_km !== null), 'MySQL distance grouping failed');
    $quote = app(\App\Services\Shipping\WarehouseDeliveryQuote::class)->calculate('smoke-house', [['product_id' => $a->id, 'quantity' => 2]], $location);
    $check($quote['available'] && $quote['subtotal'] === 2200.0, 'MySQL quote/SKU price failed');
    [$lead, $created] = app(\App\Services\Order\OneClickLeadService::class)->create(['name' => 'Smoke buyer', 'phone' => '+79990000001', 'product_id' => $a->id, 'quantity' => 1], 'mysql-smoke-idempotency-'.\Illuminate\Support\Str::uuid(), '127.0.0.1');
    $check($created && $lead->fresh()->consents['personal_data'] === true, 'MySQL lead/FK/consents failed');
    echo json_encode(['status' => 'mysql_fulfillment_smoke_passed', 'effective_price_range' => $meta['price'], 'distance_roots' => $rows->count(), 'fixtures' => 'rolled_back'], JSON_UNESCAPED_UNICODE).PHP_EOL;
} finally {
    $db->rollBack();
}
