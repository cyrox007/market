<?php

namespace Tests\Feature\Api;

use App\Actions\Product\Data\MergeProductsIntoVariableProductData;
use App\Actions\Product\MergeProductsIntoVariableProductAction;
use App\Models\Product\Attribute;
use App\Models\Product\Product;
use App\Services\Inventory\WarehouseStockResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Что видит витрина после объединения товаров.
 */
class MergedProductApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Статический кэш остатков переживает тесты, а ID товаров между ними повторяются
        WarehouseStockResolver::clearCache();
        Attribute::ensureVariantAttribute();
    }

    public function test_merged_card_exposes_every_product_as_selectable_variant(): void
    {
        $white = Product::factory()->create(['name' => 'Стенка МАРТА-11 Белый', 'price' => 43000, 'state' => 'active', 'stock' => 2]);
        $oak = Product::factory()->create(['name' => 'Стенка МАРТА-11 Венге', 'price' => 41000, 'state' => 'active', 'stock' => 0]);

        $parent = app(MergeProductsIntoVariableProductAction::class)->execute(
            new MergeProductsIntoVariableProductData(productIds: [$white->id, $oak->id]),
        );

        $response = $this->getJson('/api/v1/products/' . $parent->slug)->assertOk();

        $this->assertSame($parent->id, (int) $response->json('product.id'));
        $this->assertSame('Стенка МАРТА-11', $response->json('product.name'));
        $this->assertTrue((bool) $response->json('product.is_variable'));
        $this->assertSame(41000.0, (float) $response->json('product.price'));

        $variants = collect($response->json('product.variants'))->keyBy('id');
        $this->assertEqualsCanonicalizing([$white->id, $oak->id], $variants->keys()->all());

        $labels = $variants->map(fn (array $variant) => collect($variant['variation_attributes'])
            ->firstWhere('attribute_slug', Attribute::SLUG_VARIANT)['value_name'] ?? null);
        $this->assertSame('Белый', $labels[$white->id]);
        $this->assertSame('Венге', $labels[$oak->id]);

        $this->assertTrue((bool) $variants[$white->id]['in_stock']);
        $this->assertFalse((bool) $variants[$oak->id]['in_stock']);

        $variationAttribute = collect($response->json('product.variation_attributes'))
            ->firstWhere('attribute_slug', Attribute::SLUG_VARIANT);
        $this->assertNotNull($variationAttribute);
        $this->assertCount(2, $variationAttribute['values']);
    }

    public function test_catalog_lists_only_merged_card(): void
    {
        $a = Product::factory()->create(['name' => 'Комод К800 Белый', 'state' => 'active']);
        $b = Product::factory()->create(['name' => 'Комод К800 Венге', 'state' => 'active']);

        $parent = app(MergeProductsIntoVariableProductAction::class)->execute(
            new MergeProductsIntoVariableProductData(productIds: [$a->id, $b->id]),
        );

        $ids = collect($this->getJson('/api/v1/products?per_page=100')->assertOk()->json('data'))->pluck('id')->all();

        $this->assertContains($parent->id, $ids);
        $this->assertNotContains($a->id, $ids);
        $this->assertNotContains($b->id, $ids);
    }
}
