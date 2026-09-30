<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Inventory;

use App\Models\Inventory\ProductWarehouseStock;
use App\Models\Inventory\Warehouse;
use App\Models\Product\Product;
use App\Services\Inventory\Integrations\OneCStockSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OneCStockSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('catalog_import.config.svetofor_1c.base_url', 'http://1c.test');
        config()->set('catalog_import.config.svetofor_1c.api_key', 'test-key');
        config()->set('catalog_import.config.svetofor_1c.stock_sync_timeout', 5);
        config()->set(
            'catalog_import.config.svetofor_1c.stock_sync_path',
            '/api/v1/integration/1c/v2/cache/stocks'
        );
        config()->set(
            'catalog_import.config.svetofor_1c.stock_product_path_template',
            '/api/v1/integration/1c/v2/cache/products/{external_id}/stocks'
        );
    }

    public function test_point_sync_changes_only_stock_fields(): void
    {
        $product = Product::factory()->create([
            'name' => 'Локальное название',
            'slug' => 'local-name',
            'external_id' => 'P-100',
            'price' => 15990,
            'description' => 'Локальное описание',
            'stock' => 77,
        ]);

        $staleWarehouse = Warehouse::query()->create([
            'external_id' => 'OLD-WH',
            'name' => 'Старый склад',
            'is_active' => true,
        ]);

        ProductWarehouseStock::query()->create([
            'product_id' => $product->id,
            'warehouse_id' => $staleWarehouse->id,
            'quantity' => 12,
        ]);

        Http::fake([
            'http://1c.test/api/v1/integration/1c/v2/cache/products/P-100/stocks' => Http::response([
                ['stockId' => 'WH-1', 'stockName' => 'Основной', 'count' => 3],
                ['stockId' => 'WH-2', 'stockName' => 'Резерв', 'count' => 2],
            ]),
        ]);

        $result = app(OneCStockSyncService::class)->syncProduct($product);

        $product->refresh();

        $this->assertSame(1, $result['synced']);
        $this->assertSame(2, $result['warehouse_rows']);
        $this->assertSame('Локальное название', $product->name);
        $this->assertSame('local-name', $product->slug);
        $this->assertSame('Локальное описание', $product->description);
        $this->assertSame(15990.0, (float) $product->price);
        $this->assertSame(5.0, (float) $product->stock);

        $this->assertDatabaseHas('product_warehouse_stocks', [
            'product_id' => $product->id,
            'warehouse_id' => $staleWarehouse->id,
            'quantity' => 0,
        ]);

        Http::assertSentCount(1);
    }

    public function test_bulk_sync_updates_only_existing_products_and_never_creates_catalog_rows(): void
    {
        $product = Product::factory()->create([
            'name' => 'Товар из Ozon',
            'external_id' => 'P-200',
            'price' => 25000,
            'stock' => 0,
        ]);

        $countBefore = Product::query()->count();

        Http::fake([
            'http://1c.test/api/v1/integration/1c/v2/cache/stocks' => Http::response([
                'data' => [
                    [
                        'productExternalId' => 'P-200',
                        'stockId' => 'WH-1',
                        'stockName' => 'Основной',
                        'count' => 8,
                    ],
                    [
                        'productExternalId' => 'UNKNOWN-1C-CODE',
                        'stockId' => 'WH-1',
                        'stockName' => 'Основной',
                        'count' => 99,
                    ],
                ],
            ]),
        ]);

        $result = app(OneCStockSyncService::class)->syncAll();

        $product->refresh();

        $this->assertSame(2, $result['targets']);
        $this->assertSame(1, $result['synced']);
        $this->assertSame(1, $result['skipped']);
        $this->assertSame(8.0, (float) $product->stock);
        $this->assertSame('Товар из Ozon', $product->name);
        $this->assertSame(25000.0, (float) $product->price);
        $this->assertSame($countBefore, Product::query()->count());
        $this->assertNull(Product::query()->where('external_id', 'UNKNOWN-1C-CODE')->first());
    }

    public function test_variable_root_syncs_variants_even_when_root_has_no_1c_code(): void
    {
        $root = Product::factory()->create([
            'is_variable' => true,
            'external_id' => null,
            'sku' => null,
            'stock' => 0,
        ]);

        $first = Product::factory()->create([
            'parent_product_id' => $root->id,
            'is_variable' => false,
            'external_id' => 'VAR-1',
            'stock' => 0,
        ]);

        $second = Product::factory()->create([
            'parent_product_id' => $root->id,
            'is_variable' => false,
            'external_id' => 'VAR-2',
            'stock' => 0,
        ]);

        Http::fake(function ($request) {
            $url = $request->url();

            if (str_contains($url, '/VAR-1/stocks')) {
                return Http::response([
                    ['stockId' => 'WH-1', 'stockName' => 'Основной', 'count' => 4],
                ]);
            }

            if (str_contains($url, '/VAR-2/stocks')) {
                return Http::response([
                    ['stockId' => 'WH-1', 'stockName' => 'Основной', 'count' => 6],
                ]);
            }

            return Http::response([], 404);
        });

        $result = app(OneCStockSyncService::class)->syncProduct($root);

        $this->assertSame(3, $result['targets']);
        $this->assertSame(2, $result['synced']);
        $this->assertSame(1, $result['skipped']);
        $this->assertSame(4.0, (float) $first->fresh()->stock);
        $this->assertSame(6.0, (float) $second->fresh()->stock);
    }
}
