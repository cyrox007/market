<?php

namespace Tests\Feature\Catalog;

use App\Models\Product\Attribute;
use App\Models\Product\Product;
use App\Services\Catalog\Integrations\OpenCartXlsxCatalogImport;
use Tests\TestCase;

class OpenCartColorImportTest extends TestCase
{
    public function test_opencart_color_attribute_becomes_main_color(): void
    {
        Attribute::create(['name' => 'Цвет', 'slug' => 'opencart-7', 'type' => 'text']);
        $product = Product::factory()->create();
        $importer = new class extends OpenCartXlsxCatalogImport
        {
            public function sync(Product $product, array $attributes): void
            {
                $this->syncProductAttributes($product, $attributes);
            }
        };

        $importer->sync($product, [['attribute_id' => 7, 'value' => 'Черная'], ['attribute_id' => 8, 'value' => 'ЛДСП']]);

        $main = $product->colorRows()->whereNull('color_element_id')->sole()->solution()->with('baseColors')->sole();
        $this->assertSame('Черная', $main->name);
        $this->assertSame(['Чёрный'], $main->baseColors->pluck('name')->all());
        $this->assertSame(['opencart-8'], $product->fresh()->attributes()->pluck('slug')->all());
    }
}
