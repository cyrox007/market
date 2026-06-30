<?php

namespace Tests\Feature\Actions;

use App\Actions\Product\Data\MergeProductsIntoVariableProductData;
use App\Actions\Product\MergeProductsIntoVariableProductAction;
use App\Models\Inventory\ProductWarehouseStock;
use App\Models\Inventory\Warehouse;
use App\Models\Product\Attribute;
use App\Models\Product\AttributeValue;
use App\Models\Product\Product;
use App\Services\Catalog\Integrations\Svetofor1CCatalogImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Tests\TestCase;

class MergeProductsIntoVariableProductActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Attribute::firstOrCreate(
            ['slug' => Attribute::SLUG_VARIANT],
            [
                'name' => 'Вариант',
                'type' => 'string',
                'is_filterable' => false,
                'is_use_in_variations' => true,
                'allow_custom_value' => true,
                'sort_order' => 1,
            ]
        );
    }

    public function test_merge_three_simple_products_into_variable_parent(): void
    {
        $variantAttr = Attribute::where('slug', Attribute::SLUG_VARIANT)->firstOrFail();

        $parent = Product::factory()->create([
            'name' => 'Диван родитель',
            'price' => 5000,
            'parent_product_id' => null,
            'is_variable' => false,
        ]);
        $second = Product::factory()->create([
            'name' => 'Диван синий',
            'price' => 6000,
            'parent_product_id' => null,
            'is_variable' => false,
        ]);
        $third = Product::factory()->create([
            'name' => 'Диван красный',
            'price' => 7000,
            'parent_product_id' => null,
            'is_variable' => false,
        ]);

        $result = app(MergeProductsIntoVariableProductAction::class)->execute(
            new MergeProductsIntoVariableProductData(
                parentId: $parent->id,
                productIds: [$parent->id, $second->id, $third->id],
                variantLabelOverrides: [
                    $second->id => 'Синий',
                    $third->id => 'Красный',
                ],
            ),
        );

        $this->assertTrue($result->isVariable());
        $this->assertNull($result->parent_product_id);
        $this->assertSame(2, $result->variants()->count());

        $second->refresh();
        $third->refresh();

        $this->assertSame($parent->id, $second->parent_product_id);
        $this->assertSame($parent->id, $third->parent_product_id);
        $this->assertFalse((bool) $second->is_variable);

        $this->assertDatabaseHas('product_variant_attributes', [
            'product_id' => $second->id,
            'attribute_id' => $variantAttr->id,
            'custom_value' => 'Синий',
        ]);

        $this->assertDatabaseHas('product_variation_attribute_selection', [
            'product_id' => $parent->id,
            'attribute_id' => $variantAttr->id,
        ]);
    }

    public function test_merge_creates_variant_attribute_when_missing_in_catalog(): void
    {
        Attribute::query()->where('slug', Attribute::SLUG_VARIANT)->delete();

        $parent = Product::factory()->create(['is_variable' => false]);
        $offer = Product::factory()->create([
            'name' => 'Диван серый',
            'is_variable' => false,
        ]);

        app(MergeProductsIntoVariableProductAction::class)->execute(
            new MergeProductsIntoVariableProductData(
                parentId: $parent->id,
                productIds: [$parent->id, $offer->id],
            ),
        );

        $variantAttr = Attribute::where('slug', Attribute::SLUG_VARIANT)->first();
        $this->assertNotNull($variantAttr);

        $this->assertDatabaseHas('product_variant_attributes', [
            'product_id' => $offer->id,
            'attribute_id' => $variantAttr->id,
            'custom_value' => 'Диван серый',
        ]);
    }

    public function test_merge_auto_variant_label_from_product_name(): void
    {
        $variantAttr = Attribute::where('slug', Attribute::SLUG_VARIANT)->firstOrFail();

        $parent = Product::factory()->create(['is_variable' => false]);
        $offer = Product::factory()->create([
            'name' => 'Шкаф угловой белый',
            'is_variable' => false,
        ]);

        app(MergeProductsIntoVariableProductAction::class)->execute(
            new MergeProductsIntoVariableProductData(
                parentId: $parent->id,
                productIds: [$parent->id, $offer->id],
            ),
        );

        $this->assertDatabaseHas('product_variant_attributes', [
            'product_id' => $offer->id,
            'attribute_id' => $variantAttr->id,
            'custom_value' => 'Шкаф угловой белый',
        ]);
    }

    public function test_merge_copies_variation_attributes_from_product_characteristics(): void
    {
        $variantAttr = Attribute::where('slug', Attribute::SLUG_VARIANT)->firstOrFail();
        $colorAttr = Attribute::firstOrCreate(
            ['slug' => 'color'],
            [
                'name' => 'Цвет',
                'type' => 'color',
                'is_filterable' => true,
                'is_use_in_variations' => true,
                'allow_custom_value' => false,
                'sort_order' => 2,
            ]
        );
        $colorValue = AttributeValue::query()->create([
            'attribute_id' => $colorAttr->id,
            'value' => 'Серый',
            'slug' => 'seryi',
        ]);

        $parent = Product::factory()->create(['is_variable' => false]);
        $parent->variationAttributeSelection()->sync([$variantAttr->id, $colorAttr->id]);

        $offer = Product::factory()->create(['name' => 'Диван А', 'is_variable' => false]);
        $offer->attributes()->attach($colorAttr->id, [
            'attribute_value_id' => $colorValue->id,
            'custom_value' => null,
        ]);

        app(MergeProductsIntoVariableProductAction::class)->execute(
            new MergeProductsIntoVariableProductData(
                parentId: $parent->id,
                productIds: [$parent->id, $offer->id],
            ),
        );

        $this->assertDatabaseHas('product_variant_attributes', [
            'product_id' => $offer->id,
            'attribute_id' => $colorAttr->id,
            'attribute_value_id' => $colorValue->id,
        ]);
    }

    public function test_merge_rejects_existing_variant(): void
    {
        $parent = Product::factory()->create(['parent_product_id' => null, 'is_variable' => true]);
        $variant = Product::factory()->create([
            'parent_product_id' => $parent->id,
            'is_variable' => false,
        ]);
        $simple = Product::factory()->create(['parent_product_id' => null, 'is_variable' => false]);

        $this->expectException(InvalidArgumentException::class);

        app(MergeProductsIntoVariableProductAction::class)->execute(
            new MergeProductsIntoVariableProductData(
                parentId: $simple->id,
                productIds: [$simple->id, $variant->id],
            ),
        );
    }

    public function test_merge_rejects_parent_with_existing_variants(): void
    {
        $parent = Product::factory()->create(['parent_product_id' => null, 'is_variable' => true]);
        Product::factory()->create([
            'parent_product_id' => $parent->id,
            'is_variable' => false,
        ]);
        $other = Product::factory()->create(['parent_product_id' => null, 'is_variable' => false]);

        $this->expectException(InvalidArgumentException::class);

        app(MergeProductsIntoVariableProductAction::class)->execute(
            new MergeProductsIntoVariableProductData(
                parentId: $parent->id,
                productIds: [$parent->id, $other->id],
            ),
        );
    }

    public function test_merge_rejects_duplicate_variant_labels(): void
    {
        $parent = Product::factory()->create(['is_variable' => false]);
        $a = Product::factory()->create(['is_variable' => false]);
        $b = Product::factory()->create(['is_variable' => false]);

        $this->expectException(InvalidArgumentException::class);

        app(MergeProductsIntoVariableProductAction::class)->execute(
            new MergeProductsIntoVariableProductData(
                parentId: $parent->id,
                productIds: [$parent->id, $a->id, $b->id],
                variantLabelOverrides: [
                    $a->id => 'Одинаковый',
                    $b->id => 'Одинаковый',
                ],
            ),
        );
    }

    public function test_merge_updates_parent_name_when_provided(): void
    {
        $parent = Product::factory()->create([
            'name' => 'Старое название',
            'is_variable' => false,
        ]);
        $offer = Product::factory()->create(['is_variable' => false]);

        app(MergeProductsIntoVariableProductAction::class)->execute(
            new MergeProductsIntoVariableProductData(
                parentId: $parent->id,
                productIds: [$parent->id, $offer->id],
                parentName: 'Диван «Комфорт»',
            ),
        );

        $parent->refresh();
        $this->assertSame('Диван «Комфорт»', $parent->name);
    }

    public function test_merge_syncs_parent_price_from_variants(): void
    {
        $parent = Product::factory()->create(['price' => 9999, 'is_variable' => false]);
        $cheap = Product::factory()->create(['price' => 3000, 'is_variable' => false]);
        $dear = Product::factory()->create(['price' => 8000, 'is_variable' => false]);

        app(MergeProductsIntoVariableProductAction::class)->execute(
            new MergeProductsIntoVariableProductData(
                parentId: $parent->id,
                productIds: [$parent->id, $cheap->id, $dear->id],
                variantLabelOverrides: [
                    $cheap->id => 'Дешёвый',
                    $dear->id => 'Дорогой',
                ],
            ),
        );

        $parent->refresh();
        $this->assertSame(3000.0, (float) $parent->price);
    }

    public function test_merge_preserves_slug_sku_and_external_id(): void
    {
        $parent = Product::factory()->create([
            'slug' => 'parent-sofa',
            'sku' => 'P-001',
            'external_id' => 'uuid-parent',
            'is_variable' => false,
        ]);
        $variant = Product::factory()->create([
            'slug' => 'sofa-blue',
            'sku' => 'V-002',
            'external_id' => 'uuid-blue',
            'is_variable' => false,
        ]);

        app(MergeProductsIntoVariableProductAction::class)->execute(
            new MergeProductsIntoVariableProductData(
                parentId: $parent->id,
                productIds: [$parent->id, $variant->id],
            ),
        );

        $variant->refresh();

        $this->assertSame('sofa-blue', $variant->slug);
        $this->assertSame('V-002', $variant->sku);
        $this->assertSame('uuid-blue', $variant->external_id);
    }

    public function test_merge_preserves_warehouse_stocks(): void
    {
        $parent = Product::factory()->create(['is_variable' => false]);
        $variant = Product::factory()->create([
            'external_id' => 'wh-variant-1',
            'is_variable' => false,
        ]);

        $warehouse = Warehouse::create([
            'name' => 'Склад тест',
            'external_id' => 'stock-1',
            'is_active' => true,
        ]);

        $stock = ProductWarehouseStock::create([
            'product_id' => $variant->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 12,
        ]);

        app(MergeProductsIntoVariableProductAction::class)->execute(
            new MergeProductsIntoVariableProductData(
                parentId: $parent->id,
                productIds: [$parent->id, $variant->id],
            ),
        );

        $stock->refresh();
        $this->assertSame($variant->id, $stock->product_id);
        $this->assertSame(12.0, (float) $stock->quantity);
    }

    public function test_sync_warehouse_stocks_finds_merged_variants_by_external_id(): void
    {
        $parent = Product::factory()->create([
            'external_id' => 'parent-ext',
            'is_variable' => false,
        ]);
        $variant = Product::factory()->create([
            'external_id' => 'variant-ext-99',
            'is_variable' => false,
        ]);

        app(MergeProductsIntoVariableProductAction::class)->execute(
            new MergeProductsIntoVariableProductData(
                parentId: $parent->id,
                productIds: [$parent->id, $variant->id],
            ),
        );

        Http::fake([
            '*' => Http::response([]),
        ]);

        $import = app(Svetofor1CCatalogImport::class);
        $result = $import->syncWarehouseStocksForProduct($parent->fresh());

        $syncedIds = collect($result['synced'])->pluck('id')->all();
        $this->assertContains($variant->id, $syncedIds);
        $this->assertNotContains($variant->id, collect($result['skipped'])->pluck('id')->all());
    }
}
