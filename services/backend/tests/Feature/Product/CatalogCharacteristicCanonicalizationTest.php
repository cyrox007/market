<?php

namespace Tests\Feature\Product;

use App\Models\Product\Attribute;
use App\Models\Product\Product;
use App\Services\Catalog\Integrations\Svetofor1CCatalogImport;
use App\Services\Product\ProductColorImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogCharacteristicCanonicalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_sends_exact_color_to_main_color_and_size_to_canonical_slug(): void
    {
        $product = Product::factory()->create(['external_id' => 'product-1']);

        $importer = new class extends Svetofor1CCatalogImport
        {
            public function __construct()
            {
            }

            public function syncCharacteristics(Product $product): void
            {
                $this->syncProductCharacteristics($product, 'product-1');
            }

            protected function fetchProductCharacteristicsRaw(string $externalId): array
            {
                return [
                    ['name' => 'Цвет', 'values' => ['Серая']],
                    ['name' => 'Размер', 'values' => ['160x200']],
                    ['name' => 'Размер упаковки', 'values' => ['170x210']],
                    ['name' => 'Цвет каркаса', 'values' => ['Чёрный']],
                ];
            }
        };

        $importer->syncCharacteristics($product);

        $slugs = $product->fresh()->attributes()->pluck('slug')->all();

        $this->assertNotContains('color', $slugs);
        $this->assertContains(Attribute::SLUG_SIZE, $slugs);
        $this->assertContains('razmer-upakovki', $slugs);
        $this->assertContains('cvet-karkasa', $slugs);
        $this->assertNotContains('cvet', $slugs);
        $this->assertNotContains('razmer', $slugs);

        $main = $product->colorRows()->whereNull('color_element_id')->sole()->solution()->with('baseColors')->sole();
        $this->assertSame('Серая', $main->name);
        $this->assertSame(['Серый'], $main->baseColors->pluck('name')->all());

        $size = Attribute::query()->where('slug', Attribute::SLUG_SIZE)->firstOrFail();

        $this->assertSame('select', $size->type);
        $this->assertTrue((bool) $size->is_filterable);
        $this->assertTrue((bool) $size->is_use_in_variations);
        $this->assertTrue((bool) $size->allow_custom_value);
    }

    public function test_canonical_slug_mapping_does_not_collapse_related_but_different_characteristics(): void
    {
        $this->assertTrue(ProductColorImport::isColorField(' Цвет '));
        $this->assertFalse(ProductColorImport::isColorField('Цвет каркаса'));
        $this->assertSame(Attribute::SLUG_SIZE, Attribute::canonicalSlugForName('Размер'));
        $this->assertSame(Attribute::SLUG_SIZE, Attribute::canonicalSlugForName('size'));
        $this->assertSame('razmer-upakovki', Attribute::canonicalSlugForName('Размер упаковки'));
        $this->assertSame('cvet-karkasa', Attribute::canonicalSlugForName('Цвет каркаса'));
    }
}
