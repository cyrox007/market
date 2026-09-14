<?php

namespace Tests\Feature\Product;

use App\Models\Product\Attribute;
use App\Models\Product\Product;
use App\Services\Catalog\Integrations\Svetofor1CCatalogImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogCharacteristicCanonicalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_maps_only_exact_color_and_size_names_to_canonical_slugs(): void
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
                    ['name' => 'Цвет', 'values' => ['Серый']],
                    ['name' => 'Размер', 'values' => ['160x200']],
                    ['name' => 'Размер упаковки', 'values' => ['170x210']],
                    ['name' => 'Цвет каркаса', 'values' => ['Чёрный']],
                ];
            }
        };

        $importer->syncCharacteristics($product);

        $slugs = $product->fresh()->attributes()->pluck('slug')->all();

        $this->assertContains(Attribute::SLUG_COLOR, $slugs);
        $this->assertContains(Attribute::SLUG_SIZE, $slugs);
        $this->assertContains('razmer-upakovki', $slugs);
        $this->assertContains('tsvet-karkasa', $slugs);
        $this->assertNotContains('tsvet', $slugs);
        $this->assertNotContains('razmer', $slugs);

        $color = Attribute::query()->where('slug', Attribute::SLUG_COLOR)->firstOrFail();
        $size = Attribute::query()->where('slug', Attribute::SLUG_SIZE)->firstOrFail();

        $this->assertSame('color', $color->type);
        $this->assertTrue((bool) $color->is_filterable);
        $this->assertTrue((bool) $color->is_use_in_variations);
        $this->assertFalse((bool) $color->allow_custom_value);

        $this->assertSame('select', $size->type);
        $this->assertTrue((bool) $size->is_filterable);
        $this->assertTrue((bool) $size->is_use_in_variations);
        $this->assertTrue((bool) $size->allow_custom_value);
    }

    public function test_canonical_slug_mapping_does_not_collapse_related_but_different_characteristics(): void
    {
        $this->assertSame(Attribute::SLUG_COLOR, Attribute::canonicalSlugForName('Цвет'));
        $this->assertSame(Attribute::SLUG_COLOR, Attribute::canonicalSlugForName('color'));
        $this->assertSame(Attribute::SLUG_SIZE, Attribute::canonicalSlugForName('Размер'));
        $this->assertSame(Attribute::SLUG_SIZE, Attribute::canonicalSlugForName('size'));
        $this->assertSame('razmer-upakovki', Attribute::canonicalSlugForName('Размер упаковки'));
        $this->assertSame('tsvet-karkasa', Attribute::canonicalSlugForName('Цвет каркаса'));
    }
}
