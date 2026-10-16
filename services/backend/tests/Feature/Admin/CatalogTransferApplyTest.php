<?php

namespace Tests\Feature\Admin;

use App\Models\Inventory\Warehouse;
use App\Models\Product\Product;
use App\Services\Catalog\Transfer\CatalogTransfer;
use App\Services\Catalog\Transfer\TransferProductWriter;
use App\Services\Catalog\Transfer\TransferSources;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CatalogTransferApplyTest extends TestCase
{
    private function row(string $code = '00123', array $data = []): int
    {
        $run = DB::table('catalog_transfer_runs')->insertGetId(['status' => 'running', 'apply_enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
        $file = DB::table('catalog_transfer_files')->insertGetId(['run_id' => $run, 'name' => 'test.xlsx', 'path' => 'test.xlsx', 'mapping' => '{}', 'status' => 'read', 'created_at' => now(), 'updated_at' => now()]);

        return DB::table('catalog_transfer_rows')->insertGetId(['run_id' => $run, 'file_id' => $file, 'row_number' => 2, 'code' => $code, 'old_id' => 99, 'price' => 24990,
            'source' => json_encode(['product_data' => $data + ['name' => 'Товар из таблицы', 'manufacturer' => 'Своя фабрика']]), 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_creates_from_table_once_even_when_api_unavailable(): void
    {
        $id = $this->row();
        $run = DB::table('catalog_transfer_rows')->find($id)->run_id;
        $this->mock(TransferSources::class)->shouldReceive('enrichment')->once()->andReturn(['status' => 'not_configured']);
        $service = app(CatalogTransfer::class);
        $service->step($run);
        $this->assertDatabaseHas('products', ['external_id' => '00123', 'sku' => '00123', 'name' => 'Товар из таблицы', 'price' => 24990, 'stock' => 0]);
        $service->step($run);
        $this->assertDatabaseHas('catalog_transfer_rows', ['id' => $id, 'status' => 'applied_waiting']);
        DB::table('catalog_transfer_rows')->where('id', $id)->update(['status' => 'pending']);
        app(TransferProductWriter::class)->applyTable($id);
        $this->assertSame(1, Product::count());
    }

    public function test_legacy_sku_is_updated_without_duplicate_or_price_from_api(): void
    {
        $product = Product::factory()->create(['sku' => '00123', 'external_id' => '99', 'price' => 10]);
        $row = $this->row();
        $writer = app(TransferProductWriter::class);
        $this->assertSame($product->id, $writer->applyTable($row)->id);
        $writer->applyApi($row, ['characteristics' => [['name' => 'Материал', 'values' => ['Дерево']], ['name' => 'Производитель', 'values' => ['Не та фабрика']]], 'stocks' => [], 'price' => 1]);
        $this->assertSame(1, Product::count());
        $this->assertEquals(24990, $product->fresh()->price);
        $this->assertSame('Своя фабрика', app(\App\Services\Product\ProductManufacturer::class)->value($product->fresh()));
    }

    public function test_api_creates_missing_warehouse_then_records_stock_and_clears_stale_stock(): void
    {
        $id = $this->row();
        $writer = app(TransferProductWriter::class);
        $product = $writer->applyTable($id);
        $writer->applyApi($id, ['characteristics' => [], 'stocks' => [['stockId' => 'warehouse-1', 'stockName' => 'Новый склад', 'count' => 12]]]);
        $warehouse = Warehouse::where('external_id', 'warehouse-1')->firstOrFail();
        $this->assertDatabaseHas('product_warehouse_stocks', ['product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'quantity' => 12]);
        $this->assertEquals(12, $product->fresh()->stock);
        $writer->applyApi($id, ['characteristics' => [], 'stocks' => []]);
        $this->assertEquals(12, $product->fresh()->stock); // Already-applied checkpoint is idempotent.
        DB::table('catalog_transfer_rows')->where('id', $id)->update(['api_applied_at' => null]);
        $writer->applyApi($id, ['characteristics' => [], 'stocks' => []]);
        $this->assertEquals(0, $product->fresh()->stock);
        $this->assertSame(1, Warehouse::where('external_id', 'warehouse-1')->count());
    }

    public function test_matching_different_products_is_conflict_and_writes_nothing(): void
    {
        Product::factory()->create(['sku' => '00123', 'external_id' => '99']);
        Product::factory()->create(['sku' => 'other', 'external_id' => '00123']);
        $id = $this->row();
        $this->expectException(\DomainException::class);
        app(TransferProductWriter::class)->applyTable($id);
    }

    public function test_table_dimensions_take_priority_over_api_dimensions(): void
    {
        $id = $this->row(data: ['width' => '50', 'length_unit' => 'см']);
        $writer = app(TransferProductWriter::class);
        $product = $writer->applyTable($id);
        $writer->applyApi($id, ['characteristics' => [['name' => 'Ширина (мм)', 'values' => ['1000']]], 'stocks' => []]);
        $this->assertDatabaseHas('product_attribute_values', ['value' => '500']);
        $this->assertDatabaseMissing('product_attribute_values', ['value' => '1000']);
    }

    public function test_existing_one_c_price_sync_cannot_overwrite_table_price(): void
    {
        $row = $this->row();
        $product = app(TransferProductWriter::class)->applyTable($row);
        \Illuminate\Support\Facades\Http::preventStrayRequests();
        $this->assertFalse(app(\App\Services\Catalog\Integrations\Svetofor1CCatalogImport::class)->syncPriceByExternalId('00123'));
        $this->assertEquals(24990, $product->fresh()->price);
        \Illuminate\Support\Facades\Http::assertNothingSent();
    }

    public function test_full_gallery_is_merged_with_table_images_and_resume_does_not_duplicate_media(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        \Illuminate\Support\Facades\Queue::fake();
        config(['media-library.disk_name' => 'public']);
        $rowId = $this->row();
        $row = DB::table('catalog_transfer_rows')->find($rowId);
        $source = json_decode($row->source, true);
        $source['images'] = ['https://svetofor-mebel.ru/image/main.png'];
        $source['category_paths'] = [];
        DB::table('catalog_transfer_rows')->where('id', $rowId)->update(['source' => json_encode($source)]);
        $product = app(TransferProductWriter::class)->applyTable($rowId);
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aDagAAAAASUVORK5CYII=');
        $this->mock(TransferSources::class, function ($mock) use ($png): void {
            $mock->shouldReceive('card')->once()->with(99)->andReturn(['images' => ['https://svetofor-mebel.ru/image/gallery-2.png', 'https://svetofor-mebel.ru/image/gallery-3.png'], 'category_navigation' => []]);
            $mock->shouldReceive('image')->times(3)->andReturn(['bytes' => $png, 'extension' => 'png']);
        });
        $service = app(\App\Services\Catalog\Transfer\TransferAssets::class);
        for ($i = 0; $i < 5; $i++) {
            $service->step($row->run_id);
        }
        $state = json_decode(DB::table('catalog_transfer_rows')->where('id', $rowId)->value('assets_result'), true);
        $this->assertCount(3, $state['images']);
        $this->assertCount(3, $state['completed']);
        $this->assertSame([], $state['warnings']);
        $this->assertSame(1, $product->media()->count()); // Identical binary contents are deduplicated too.
        $this->assertSame(-1, $service->step($row->run_id));
        $this->assertDatabaseHas('catalog_transfer_rows', ['id' => $rowId, 'assets_done' => true]);
    }
}
