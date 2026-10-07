<?php

namespace Tests\Feature\Actions;

use App\Actions\Product\BuildMergedProductDraftAction;
use App\Actions\Product\Data\MergeProductsIntoVariableProductData;
use App\Actions\Product\MergeProductsIntoVariableProductAction;
use App\Models\Inventory\ProductWarehouseStock;
use App\Models\Inventory\Warehouse;
use App\Models\Product\Attribute;
use App\Models\Product\AttributeValue;
use App\Models\Product\Category;
use App\Models\Product\Product;
use App\Services\Catalog\Integrations\Svetofor1CCatalogImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
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

    public function test_merge_creates_new_parent_and_turns_every_product_into_variant(): void
    {
        $variantAttr = Attribute::where('slug', Attribute::SLUG_VARIANT)->firstOrFail();

        $products = collect(['Диван белый', 'Диван синий', 'Диван красный'])
            ->map(fn (string $name) => Product::factory()->create([
                'name' => $name,
                'parent_product_id' => null,
                'is_variable' => false,
            ]));

        $parent = $this->merge($products->pluck('id')->all());

        $this->assertNotContains($parent->id, $products->pluck('id')->all());
        $this->assertTrue($parent->isVariable());
        $this->assertNull($parent->parent_product_id);
        $this->assertNull($parent->external_id);
        $this->assertSame(MergeProductsIntoVariableProductAction::PARENT_SKU_PREFIX . $parent->id, $parent->sku);
        $this->assertSame(3, $parent->variants()->count());

        foreach ($products as $product) {
            $product->refresh();
            $this->assertSame($parent->id, $product->parent_product_id);
            $this->assertFalse((bool) $product->is_variable);
        }

        $this->assertDatabaseHas('product_variant_attributes', [
            'product_id' => $products[0]->id,
            'attribute_id' => $variantAttr->id,
            'custom_value' => 'Белый',
        ]);

        $this->assertDatabaseHas('product_variation_attribute_selection', [
            'product_id' => $parent->id,
            'attribute_id' => $variantAttr->id,
        ]);
    }

    public function test_merge_builds_name_and_labels_from_one_c_names(): void
    {
        $variantAttr = Attribute::where('slug', Attribute::SLUG_VARIANT)->firstOrFail();

        $white = Product::factory()->create(['name' => '001.007.003 Журнальный стол Консул-1 белый']);
        $oak = Product::factory()->create(['name' => '001.007.002 Журнальный стол Консул-1 дуб сонома/ясень шимо']);

        $parent = $this->merge([$white->id, $oak->id]);

        $this->assertSame('Журнальный стол Консул-1', $parent->name);
        $this->assertNotSame('', (string) $parent->slug);
        $this->assertStringNotContainsString('dub', (string) $parent->slug);

        $this->assertDatabaseHas('product_variant_attributes', [
            'product_id' => $white->id,
            'attribute_id' => $variantAttr->id,
            'custom_value' => 'Белый',
        ]);
        $this->assertDatabaseHas('product_variant_attributes', [
            'product_id' => $oak->id,
            'attribute_id' => $variantAttr->id,
            'custom_value' => 'Дуб сонома/ясень шимо',
        ]);
    }

    public function test_merge_of_identically_named_products_requires_manual_labels(): void
    {
        $a = Product::factory()->create(['name' => '001.001.082 Кровать Сон-5']);
        $b = Product::factory()->create(['name' => '001.001.083 Кровать Сон-5']);

        try {
            $this->merge([$a->id, $b->id]);
            $this->fail('Одинаковые подписи должны остановить объединение');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Кровать Сон-5', $e->getMessage());
        }

        $parent = $this->merge([$a->id, $b->id], [$a->id => '1,4 м', $b->id => '1,6 м']);
        $this->assertSame('Кровать Сон-5', $parent->name);
    }

    public function test_merge_without_common_name_keeps_full_names_as_labels(): void
    {
        $variantAttr = Attribute::where('slug', Attribute::SLUG_VARIANT)->firstOrFail();

        $sofa = Product::factory()->create(['name' => '001.002.001 Диван Лион']);
        $chair = Product::factory()->create(['name' => '001.002.002 Кресло Лион']);

        $parent = $this->merge([$sofa->id, $chair->id]);

        $this->assertSame('Диван Лион', $parent->name);
        $this->assertDatabaseHas('product_variant_attributes', [
            'product_id' => $chair->id,
            'attribute_id' => $variantAttr->id,
            'custom_value' => 'Кресло Лион',
        ]);
    }

    public function test_merge_uses_name_and_label_overrides(): void
    {
        $variantAttr = Attribute::where('slug', Attribute::SLUG_VARIANT)->firstOrFail();

        $a = Product::factory()->create(['name' => 'Шкаф А']);
        $b = Product::factory()->create(['name' => 'Шкаф Б']);

        $parent = $this->merge([$a->id, $b->id], [$a->id => 'Левый'], 'Шкаф «Комфорт»');

        $this->assertSame('Шкаф «Комфорт»', $parent->name);
        $this->assertDatabaseHas('product_variant_attributes', [
            'product_id' => $a->id,
            'attribute_id' => $variantAttr->id,
            'custom_value' => 'Левый',
        ]);
        $this->assertDatabaseHas('product_variant_attributes', [
            'product_id' => $b->id,
            'attribute_id' => $variantAttr->id,
            'custom_value' => 'Б',
        ]);
    }

    public function test_merge_creates_variant_attribute_when_missing_in_catalog(): void
    {
        Attribute::query()->where('slug', Attribute::SLUG_VARIANT)->delete();

        $a = Product::factory()->create(['name' => 'Диван серый']);
        $b = Product::factory()->create(['name' => 'Диван бежевый']);

        $this->merge([$a->id, $b->id]);

        $variantAttr = Attribute::where('slug', Attribute::SLUG_VARIANT)->first();
        $this->assertNotNull($variantAttr);

        $this->assertDatabaseHas('product_variant_attributes', [
            'product_id' => $a->id,
            'attribute_id' => $variantAttr->id,
            'custom_value' => 'Серый',
        ]);
    }

    public function test_merge_copies_variation_attributes_from_product_characteristics(): void
    {
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
        $gray = AttributeValue::query()->create(['attribute_id' => $colorAttr->id, 'value' => 'Серый', 'slug' => 'seryi']);
        $blue = AttributeValue::query()->create(['attribute_id' => $colorAttr->id, 'value' => 'Синий', 'slug' => 'sinii']);

        $a = Product::factory()->create(['name' => 'Диван А']);
        $a->attributes()->attach($colorAttr->id, ['attribute_value_id' => $gray->id, 'custom_value' => null]);
        $b = Product::factory()->create(['name' => 'Диван Б']);
        $b->attributes()->attach($colorAttr->id, ['attribute_value_id' => $blue->id, 'custom_value' => null]);

        $this->merge([$a->id, $b->id]);

        $this->assertDatabaseHas('product_variant_attributes', [
            'product_id' => $a->id,
            'attribute_id' => $colorAttr->id,
            'attribute_value_id' => $gray->id,
        ]);
        $this->assertDatabaseHas('product_variant_attributes', [
            'product_id' => $b->id,
            'attribute_id' => $colorAttr->id,
            'attribute_value_id' => $blue->id,
        ]);
    }

    public function test_merge_always_unions_categories_and_warns_when_they_differ(): void
    {
        $tables = Category::factory()->create(['name' => 'Столы', 'slug' => 'stoly']);
        $wardrobes = Category::factory()->create(['name' => 'Шкафы', 'slug' => 'shkafy']);

        $a = Product::factory()->create(['name' => 'Стол белый']);
        $a->taxons()->attach($tables->id);
        $b = Product::factory()->create(['name' => 'Стол чёрный']);
        $b->taxons()->attach($wardrobes->id);

        $draft = app(BuildMergedProductDraftAction::class)->execute(Product::query()->whereIn('id', [$a->id, $b->id])->get());
        $this->assertTrue($draft->categoriesDiffer);
        $this->assertNotEmpty($draft->warnings);

        $parent = $this->merge([$a->id, $b->id]);

        $this->assertEqualsCanonicalizing([$tables->id, $wardrobes->id], $parent->taxons()->pluck('id')->all());
    }

    public function test_draft_reports_same_categories_without_warning(): void
    {
        $tables = Category::factory()->create(['name' => 'Столы', 'slug' => 'stoly']);

        $a = Product::factory()->create(['name' => 'Стол белый', 'description' => null]);
        $a->taxons()->attach($tables->id);
        $b = Product::factory()->create(['name' => 'Стол чёрный', 'description' => null]);
        $b->taxons()->attach($tables->id);

        $draft = app(BuildMergedProductDraftAction::class)->execute(Product::query()->whereIn('id', [$a->id, $b->id])->get());

        $this->assertFalse($draft->categoriesDiffer);
        $this->assertSame([], $draft->warnings);
        $this->assertSame([$tables->id], $draft->taxonIds);
    }

    public function test_merge_copies_only_common_characteristics_to_parent(): void
    {
        $width = Attribute::create(['name' => 'Ширина (мм)', 'slug' => 'sirina-mm', 'type' => 'string', 'allow_custom_value' => true]);
        $color = Attribute::create(['name' => 'Цвет столешницы', 'slug' => 'cvet-stolesnicy', 'type' => 'string', 'allow_custom_value' => true]);
        $height = Attribute::create(['name' => 'Высота (мм)', 'slug' => 'vysota-mm', 'type' => 'string', 'allow_custom_value' => true]);

        $a = Product::factory()->create(['name' => 'Стол белый']);
        $a->attributes()->attach($width->id, ['custom_value' => '900']);
        $a->attributes()->attach($color->id, ['custom_value' => 'Белый']);
        $a->attributes()->attach($height->id, ['custom_value' => '560']);

        // Высота заполнена только у одного товара — характеристика общая
        $b = Product::factory()->create(['name' => 'Стол бежевый']);
        $b->attributes()->attach($width->id, ['custom_value' => '900']);
        $b->attributes()->attach($color->id, ['custom_value' => 'Бежевый']);

        $parent = $this->merge([$a->id, $b->id]);
        $parentAttributes = $parent->attributes()->get()->keyBy('id');

        $this->assertSame('900', $parentAttributes[$width->id]->pivot->custom_value);
        $this->assertSame('560', $parentAttributes[$height->id]->pivot->custom_value);
        $this->assertFalse($parentAttributes->has($color->id));
        $this->assertSame(3, $a->fresh()->attributes()->count());
    }

    public function test_merge_takes_longest_description_and_warns_when_descriptions_differ(): void
    {
        $a = Product::factory()->create(['name' => 'Стол белый', 'description' => 'Коротко']);
        $b = Product::factory()->create(['name' => 'Стол чёрный', 'description' => 'Подробное описание стола']);

        $draft = app(BuildMergedProductDraftAction::class)->execute(Product::query()->whereIn('id', [$a->id, $b->id])->get());
        $this->assertNotEmpty($draft->warnings);

        $parent = $this->merge([$a->id, $b->id]);

        $this->assertSame('Подробное описание стола', $parent->description);
    }

    public function test_merge_copies_main_image_from_first_product_with_image(): void
    {
        Storage::fake(config('media-library.disk_name', 'public'));

        $a = Product::factory()->create(['name' => 'Стол белый']);
        $b = Product::factory()->create(['name' => 'Стол чёрный']);
        $b->addMedia(UploadedFile::fake()->image('table.jpg', 100, 100))->toMediaCollection('images');

        $parent = $this->merge([$a->id, $b->id]);

        $this->assertNotNull($parent->getFirstMedia('images'));
        $this->assertNotNull($b->fresh()->getFirstMedia('images'));
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

        $this->merge([$simple->id, $variant->id]);
    }

    public function test_merge_rejects_product_with_existing_variants(): void
    {
        $parent = Product::factory()->create(['parent_product_id' => null, 'is_variable' => true]);
        Product::factory()->create([
            'parent_product_id' => $parent->id,
            'is_variable' => false,
        ]);
        $other = Product::factory()->create(['parent_product_id' => null, 'is_variable' => false]);

        $this->expectException(InvalidArgumentException::class);

        $this->merge([$parent->id, $other->id]);
    }

    public function test_merge_rejects_duplicate_variant_labels(): void
    {
        $a = Product::factory()->create(['is_variable' => false]);
        $b = Product::factory()->create(['is_variable' => false]);

        $this->expectException(InvalidArgumentException::class);

        $this->merge([$a->id, $b->id], [
            $a->id => 'Одинаковый',
            $b->id => 'Одинаковый',
        ]);
    }

    public function test_merge_does_not_create_parent_when_validation_fails(): void
    {
        $a = Product::factory()->create(['is_variable' => false]);
        $b = Product::factory()->create(['is_variable' => false]);
        $before = Product::query()->count();

        try {
            $this->merge([$a->id, $b->id], [$a->id => 'Одинаковый', $b->id => 'Одинаковый']);
        } catch (InvalidArgumentException) {
        }

        $this->assertSame($before, Product::query()->count());
        $this->assertNull($a->fresh()->parent_product_id);
    }

    public function test_merge_syncs_parent_price_from_all_variants(): void
    {
        $first = Product::factory()->create(['name' => 'Стол А', 'price' => 3000, 'is_variable' => false]);
        $second = Product::factory()->create(['name' => 'Стол Б', 'price' => 8000, 'is_variable' => false]);

        $parent = $this->merge([$second->id, $first->id]);

        $this->assertSame(3000.0, (float) $parent->fresh()->price);
    }

    public function test_merge_preserves_slug_sku_and_external_id_of_every_product(): void
    {
        $a = Product::factory()->create([
            'slug' => 'sofa-white',
            'sku' => 'P-001',
            'external_id' => 'uuid-white',
        ]);
        $b = Product::factory()->create([
            'slug' => 'sofa-blue',
            'sku' => 'V-002',
            'external_id' => 'uuid-blue',
        ]);

        $this->merge([$a->id, $b->id]);

        foreach ([[$a, 'sofa-white', 'P-001', 'uuid-white'], [$b, 'sofa-blue', 'V-002', 'uuid-blue']] as [$product, $slug, $sku, $externalId]) {
            $product->refresh();
            $this->assertSame($slug, $product->slug);
            $this->assertSame($sku, $product->sku);
            $this->assertSame($externalId, $product->external_id);
        }
    }

    public function test_merge_preserves_warehouse_stocks(): void
    {
        $a = Product::factory()->create(['is_variable' => false]);
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

        $this->merge([$a->id, $variant->id]);

        $stock->refresh();
        $this->assertSame($variant->id, $stock->product_id);
        $this->assertSame(12.0, (float) $stock->quantity);
    }

    public function test_sync_warehouse_stocks_finds_all_merged_variants_by_external_id(): void
    {
        $a = Product::factory()->create(['external_id' => 'variant-ext-1']);
        $b = Product::factory()->create(['external_id' => 'variant-ext-2']);

        $parent = $this->merge([$a->id, $b->id]);

        Http::fake([
            '*' => Http::response([]),
        ]);

        $result = app(Svetofor1CCatalogImport::class)->syncWarehouseStocksForProduct($parent->fresh());

        $syncedIds = collect($result['synced'])->pluck('id')->all();
        $this->assertContains($a->id, $syncedIds);
        $this->assertContains($b->id, $syncedIds);
    }

    /**
     * @param  list<int>  $productIds
     * @param  array<int, string>  $labels
     */
    protected function merge(array $productIds, array $labels = [], ?string $name = null): Product
    {
        return app(MergeProductsIntoVariableProductAction::class)->execute(
            new MergeProductsIntoVariableProductData(
                productIds: $productIds,
                variantLabelOverrides: $labels,
                name: $name,
            ),
        );
    }
}
