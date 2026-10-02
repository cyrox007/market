<?php

declare(strict_types=1);

namespace Tests\Feature\Actions\Inventory;

use App\Actions\Inventory\Sync\RefreshProductStocksFrom1CAction;
use App\Jobs\Integration\DispatchAllProductStockRefreshFrom1CJob;
use App\Jobs\Integration\RefreshProductStockFrom1CJob;
use App\Models\Inventory\ProductWarehouseStock;
use App\Models\Inventory\Warehouse;
use App\Models\Product\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OneCStockRefreshTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('catalog_import.config.svetofor_1c.base_url', 'http://1c.test');
        config()->set('catalog_import.config.svetofor_1c.api_key', 'test-key');
        config()->set('catalog_import.config.svetofor_1c.stock_refresh_timeout', 5);
        config()->set(
            'catalog_import.config.svetofor_1c.stock_product_path_template',
            '/api/v1/integration/1c/v2/cache/products/{external_id}/stocks'
        );
    }

    public function test_point_refresh_updates_only_stocks(): void
    {
        $product = Product::factory()->create([
            'name' => 'Товар из Ozon',
            'slug' => 'ozon-product',
            'external_id' => 'P-100',
            'price' => 15990,
            'description' => 'Описание из карточки',
            'stock' => 77,
        ]);

        $oldWarehouse = Warehouse::query()->create([
            'external_id' => 'OLD-WH',
            'name' => 'Старый склад',
            'is_active' => true,
        ]);

        ProductWarehouseStock::query()->create([
            'product_id' => $product->id,
            'warehouse_id' => $oldWarehouse->id,
            'quantity' => 12,
        ]);

        Http::fake([
            'http://1c.test/api/v1/integration/1c/v2/cache/products/P-100/stocks' => Http::response([
                ['stockId' => 'WH-1', 'stockName' => 'Основной', 'count' => 3],
                ['stockId' => 'WH-2', 'stockName' => 'Резерв', 'count' => 2],
            ]),
        ]);

        $result = app(RefreshProductStocksFrom1CAction::class)->execute($product);

        $product->refresh();

        $this->assertSame(1, $result['synced']);
        $this->assertSame(0, $result['errors']);
        $this->assertSame(2, $result['warehouse_rows']);

        $this->assertSame('Товар из Ozon', $product->name);
        $this->assertSame('ozon-product', $product->slug);
        $this->assertSame('Описание из карточки', $product->description);
        $this->assertSame(15990.0, (float) $product->price);
        $this->assertSame(5.0, (float) $product->stock);

        $this->assertDatabaseHas('product_warehouse_stocks', [
            'product_id' => $product->id,
            'warehouse_id' => $oldWarehouse->id,
            'quantity' => 0,
        ]);
    }

    public function test_variable_root_refreshes_variants_and_skips_root_without_external_id(): void
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
            if (str_contains($request->url(), '/VAR-1/stocks')) {
                return Http::response([
                    ['stockId' => 'WH-1', 'stockName' => 'Основной', 'count' => 4],
                ]);
            }

            if (str_contains($request->url(), '/VAR-2/stocks')) {
                return Http::response([
                    ['stockId' => 'WH-1', 'stockName' => 'Основной', 'count' => 6],
                ]);
            }

            return Http::response([], 404);
        });

        $result = app(RefreshProductStocksFrom1CAction::class)->execute($root);

        $this->assertSame(3, $result['targets']);
        $this->assertSame(2, $result['synced']);
        $this->assertSame(1, $result['skipped']);
        $this->assertSame(0, $result['errors']);

        $this->assertSame(4.0, (float) $first->fresh()->stock);
        $this->assertSame(6.0, (float) $second->fresh()->stock);
    }

    public function test_unknown_external_id_does_not_create_product(): void
    {
        $countBefore = Product::query()->count();

        $result = app(RefreshProductStocksFrom1CAction::class)
            ->executeByExternalId('UNKNOWN-CODE');

        $this->assertSame(0, $result['synced']);
        $this->assertSame(1, $result['skipped']);
        $this->assertSame($countBefore, Product::query()->count());

        Http::assertNothingSent();
    }

    public function test_api_failure_keeps_existing_stock_unchanged(): void
    {
        $product = Product::factory()->create([
            'external_id' => 'FAIL-1',
            'stock' => 11,
        ]);

        Http::fake([
            'http://1c.test/api/v1/integration/1c/v2/cache/products/FAIL-1/stocks' => Http::response(
                ['message' => 'offline'],
                503
            ),
        ]);

        $result = app(RefreshProductStocksFrom1CAction::class)->execute($product);

        $this->assertSame(1, $result['errors']);
        $this->assertSame(0, $result['synced']);
        $this->assertSame(11.0, (float) $product->fresh()->stock);
    }

    public function test_global_dispatch_queues_only_existing_positions_with_external_id(): void
    {
        Queue::fake();

        Product::factory()->create(['external_id' => 'A-1']);
        Product::factory()->create(['external_id' => 'A-2']);
        Product::factory()->create(['external_id' => null]);

        (new DispatchAllProductStockRefreshFrom1CJob())->handle();

        Queue::assertPushed(RefreshProductStockFrom1CJob::class, 2);
    }
}
