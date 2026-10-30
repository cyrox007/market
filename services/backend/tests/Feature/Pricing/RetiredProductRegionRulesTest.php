<?php

namespace Tests\Feature\Pricing;

use App\Filament\Resources\Products\ProductResource;
use App\Models\Pricing\PricingRule;
use App\Models\Product\Category;
use App\Models\Product\Product;
use App\Models\Product\ProductRegionRule;
use App\Models\Shipping\ShippingLocation;
use App\Services\Pricing\PricingEngine;
use App\Services\Product\CatalogPrice;
use App\Services\Product\ProductRegionRuleService;
use Tests\TestCase;

class RetiredProductRegionRulesTest extends TestCase
{
    public function test_historical_price_and_hiding_rules_have_no_effect_on_the_catalogue(): void
    {
        $category = Category::factory()->create();
        $sku = Product::factory()->create(['state' => 'active', 'price' => 1000]);
        $sku->taxons()->attach($category->id);
        $location = ShippingLocation::factory()->create();

        ProductRegionRule::create([
            'product_id' => $sku->id,
            'shipping_location_id' => $location->id,
            'price_override' => 3000,
            'is_hidden' => true,
            'is_active' => true,
        ]);

        $service = app(ProductRegionRuleService::class);
        $service->preloadRulesForLocation($location);
        $this->assertTrue($service->isVisibleInRegion($sku, $location));
        $this->assertEquals(1000, $service->getLegacyPriceForRegion($sku, $location));
        $this->assertEquals(1000, $service->getPriceForRegion($sku, $location));
        $this->assertEquals(1000, app(CatalogPrice::class)->rows($location, false)
            ->where('products.id', $sku->id)->first()->effective_price);

        $list = $this->getJson('/api/v1/products?category_slug='.$category->slug.'&region_id='.$location->id)
            ->assertOk();
        $this->assertContains($sku->id, array_column($list->json('data'), 'id'));
        $this->assertEquals(1000, collect($list->json('data'))->firstWhere('id', $sku->id)['price']);
        $this->getJson('/api/v1/products/'.$sku->slug.'?region_id='.$location->id)
            ->assertOk()->assertJsonPath('product.id', $sku->id);
    }

    public function test_price_policy_still_applies_after_retiring_old_region_rules(): void
    {
        $sku = Product::factory()->create(['state' => 'active', 'price' => 1000]);
        $location = ShippingLocation::factory()->create();
        ProductRegionRule::create([
            'product_id' => $sku->id,
            'shipping_location_id' => $location->id,
            'price_override' => 2500,
            'is_active' => true,
        ]);
        PricingRule::create([
            'name' => 'Скидка по ценовой политике',
            'is_active' => true,
            'target_type' => 'all',
            'audience_type' => 'all',
            'adjustment_type' => 'discount_percent',
            'amount' => 10,
            'priority' => 0,
        ]);

        $this->assertEquals(900, app(PricingEngine::class)->explain($sku, $location)['price']);
        $this->assertEquals(900, app(ProductRegionRuleService::class)->getPriceForRegion($sku, $location));
    }

    public function test_deprecated_region_rule_tabs_are_absent_from_the_product_editor(): void
    {
        $relations = ProductResource::getRelations();
        $this->assertNotContains(
            \App\Filament\Resources\Products\RelationManagers\ProductRegionRulesRelationManager::class,
            $relations
        );
        $this->assertNotContains(
            \App\Filament\Resources\Products\RelationManagers\VariantRegionRulesRelationManager::class,
            $relations
        );
    }
}
