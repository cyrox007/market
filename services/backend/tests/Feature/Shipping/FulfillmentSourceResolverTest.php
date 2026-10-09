<?php

namespace Tests\Feature\Shipping;

use App\Models\Inventory\SourceProductAvailability;
use App\Models\Inventory\Warehouse;
use App\Models\Product\Product;
use App\Models\Shipping\ShippingLocation;
use App\Services\Shipping\FulfillmentSourceResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FulfillmentSourceResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_resolves_physical_and_manufacturer_sources_for_child_location(): void
    {
        $region = ShippingLocation::factory()->create();
        $city = ShippingLocation::factory()->create(['parent_id' => $region->id]);
        $product = Product::factory()->create();

        $physical = Warehouse::create(['name' => 'Склад Воронеж', 'stock_mode' => 'quantity']);
        $physical->productStocks()->create(['product_id' => $product->id, 'quantity' => 3]);
        $physicalProfile = $physical->deliveryProfiles()->create([
            'name' => 'Липецкое направление', 'base_price' => 4500,
            'delivery_days_min' => 2, 'delivery_days_max' => 4,
        ]);
        $physicalProfile->locations()->attach($region);

        $factory = Warehouse::create([
            'name' => 'Фабрика', 'source_type' => 'manufacturer', 'stock_mode' => 'availability',
            'processing_days_min' => 10, 'processing_days_max' => 14,
        ]);
        SourceProductAvailability::create([
            'warehouse_id' => $factory->id, 'product_id' => $product->id,
            'available_to_order' => true, 'processing_days_min' => 12, 'processing_days_max' => 16,
        ]);
        $factoryProfile = $factory->deliveryProfiles()->create([
            'name' => 'ЦФО', 'base_price' => 3000,
            'delivery_days_min' => 3, 'delivery_days_max' => 5,
        ]);
        $factoryProfile->locations()->attach($region);

        $sources = app(FulfillmentSourceResolver::class)->resolve($product, $city);

        $this->assertCount(2, $sources);
        $this->assertSame('Склад Воронеж', $sources->first()['source_name']);
        $this->assertSame(12, $sources->last()['processing_days_min']);
    }

    public function test_it_excludes_source_without_stock_or_availability(): void
    {
        $location = ShippingLocation::factory()->create();
        $product = Product::factory()->create();
        $warehouse = Warehouse::create(['name' => 'Пустой склад']);
        $profile = $warehouse->deliveryProfiles()->create([
            'name' => 'Регион', 'base_price' => 1000,
            'delivery_days_min' => 1, 'delivery_days_max' => 2,
        ]);
        $profile->locations()->attach($location);

        $this->assertEmpty(app(FulfillmentSourceResolver::class)->resolve($product, $location));
    }

    public function test_distance_ranks_only_eligible_sources_and_unknown_points_last(): void
    {
        $location = ShippingLocation::factory()->create();
        $product = Product::factory()->create();
        foreach ([['Unknown', null], ['Far', 54], ['Near', 52]] as [$name, $latitude]) {
            $warehouse = Warehouse::create(['name' => $name, 'latitude' => $latitude, 'longitude' => $latitude !== null ? 39 : null]);
            $warehouse->productStocks()->create(['product_id' => $product->id, 'quantity' => 1]);
            $profile = $warehouse->deliveryProfiles()->create([
                'name' => 'Доставка', 'base_price' => 1000, 'delivery_days_min' => 1, 'delivery_days_max' => 2,
            ]);
            $profile->locations()->attach($location);
        }
        $sources = app(FulfillmentSourceResolver::class)->resolve($product, $location, ['latitude' => 51, 'longitude' => 39]);
        $this->assertSame(['Near', 'Far', 'Unknown'], $sources->pluck('source_name')->all());
        $this->assertGreaterThan(100, $sources->first()['distance_km']);
        $this->assertNull($sources->last()['distance_km']);
    }
}
