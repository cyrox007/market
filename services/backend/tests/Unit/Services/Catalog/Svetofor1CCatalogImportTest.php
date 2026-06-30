<?php

namespace Tests\Unit\Services\Catalog;

use App\Models\CatalogImportRun;
use App\Models\Product\Product;
use App\Services\Catalog\Integrations\Svetofor1CCatalogImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Svetofor1CCatalogImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_uses_last_success_run_as_updated_after_watermark(): void
    {
        CatalogImportRun::query()->create([
            'importer_class' => Svetofor1CCatalogImport::class,
            'status' => CatalogImportRun::STATUS_SUCCESS,
            'created_categories' => 0,
            'updated_categories' => 0,
            'created_products' => 1,
            'updated_products' => 0,
            'started_at' => now()->subMinute(),
            'finished_at' => now()->subSeconds(5),
        ]);

        $importer = new Svetofor1CCatalogImport();
        $method = new \ReflectionMethod($importer, 'resolveUpdatedAfterIso8601');
        $method->setAccessible(true);

        $value = $method->invoke($importer);

        $this->assertIsString($value);
        $this->assertStringContainsString('T', $value);
        $this->assertStringEndsWith('Z', $value);
    }

    public function test_minute_sync_updates_only_price_for_existing_products_and_uses_full_flow_for_new(): void
    {
        $existing = Product::factory()->create([
            'name' => 'Old Name',
            'slug' => 'old-name',
            'external_id' => null,
            'sku' => 'existing-sku',
            'price' => 1000,
            'description' => 'Old description',
            'parent_product_id' => null,
        ]);

        $importer = new class extends Svetofor1CCatalogImport
        {
            public int $persistProductsCalls = 0;

            public function __construct()
            {
            }

            protected function fetchProductsRaw(?string $updatedAfter = null): array
            {
                return [];
            }

            protected function mapProducts(array $raw): array
            {
                return [
                    [
                        'name' => 'New Name Should Not Be Applied',
                        'slug' => 'new-name-should-not-be-applied',
                        'sku' => 'existing-sku',
                        'price' => 1500,
                        'description' => 'New Description Should Not Be Applied',
                        'excerpt' => null,
                        'original_price' => null,
                        'category_external_ids' => [],
                        'external_id' => 'existing-1',
                        '_payload_keys' => ['basePrice', 'name', 'description'],
                    ],
                    [
                        'name' => 'Brand New Product',
                        'slug' => 'brand-new-product',
                        'sku' => 'new-sku',
                        'price' => 2200,
                        'description' => 'new desc',
                        'excerpt' => null,
                        'original_price' => null,
                        'category_external_ids' => [],
                        'external_id' => 'new-1',
                        '_payload_keys' => ['basePrice', 'name', 'description'],
                    ],
                ];
            }

            protected function persistProducts(array $mapped): array
            {
                $this->persistProductsCalls++;
                foreach ($mapped as $row) {
                    Product::factory()->create([
                        'name' => $row['name'],
                        'slug' => $row['slug'],
                        'sku' => $row['sku'],
                        'price' => $row['price'],
                        'description' => $row['description'],
                        'external_id' => $row['external_id'],
                        'parent_product_id' => null,
                    ]);
                }

                return [count($mapped), 0];
            }
        };

        $result = $importer->importChangedProducts('2026-04-06T10:00:00.000Z');

        $existing->refresh();
        $this->assertSame('Old Name', $existing->name);
        $this->assertSame('old-name', $existing->slug);
        $this->assertSame('Old description', $existing->description);
        $this->assertSame('existing-1', $existing->external_id);
        $this->assertSame(1500.0, (float) $existing->price);

        $new = Product::query()->where('external_id', 'new-1')->first();
        $this->assertNotNull($new);
        $this->assertSame('Brand New Product', $new->name);

        $this->assertSame(1, $importer->persistProductsCalls);
        $this->assertSame(1, $result['created']);
        $this->assertSame(1, $result['updated']);
    }
}
