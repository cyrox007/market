<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\CatalogTransferPage;
use App\Models\Product\Product;
use App\Models\User;
use App\Services\Catalog\Transfer\CatalogTransfer;
use App\Services\Catalog\Transfer\OpenCartCardParser;
use App\Services\Catalog\Transfer\TransferRetry;
use App\Services\Catalog\Transfer\TransferSources;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CatalogTransferTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Http::preventStrayRequests();
        config(['catalog_import.config.svetofor_1c.api_key' => '', 'catalog_import.config.svetofor_1c.base_url' => '']);
    }

    private function runWithFile(string $format = 'Xlsx', array $data = [['00123', '56', '24990']]): int
    {
        $run = DB::table('catalog_transfer_runs')->insertGetId(['status' => 'running', 'created_at' => now(), 'updated_at' => now()]);
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->fromArray([['sku', 'product_id', 'price'], ...$data]);
        $sheet->getStyle('A2')->getNumberFormat()->setFormatCode('00000');
        $path = 'test-'.$run.'.'.strtolower($format);
        ($format === 'Xlsx' ? new Xlsx($book) : new Xls($book))->save(Storage::disk('local')->path($path));
        $book->disconnectWorksheets();
        DB::table('catalog_transfer_files')->insert([
            'run_id' => $run, 'name' => $path, 'path' => $path,
            'mapping' => json_encode(['sheet' => '', 'header' => 1, 'code' => 'A', 'old_id' => 'B', 'price' => 'C']),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $run;
    }

    public function test_xls_and_xlsx_preserve_zero_codes_and_checkpoints(): void
    {
        foreach (['Xlsx', 'Xls'] as $format) {
            $run = $this->runWithFile($format, [[123, '56', '24990']]);
            $service = app(CatalogTransfer::class);
            $service->step($run);
            $this->assertDatabaseHas('catalog_transfer_rows', ['run_id' => $run, 'code' => '00123', 'old_id' => 56]);
            // Simulate a resumed file at a previous checkpoint: upsert key prevents duplicate rows.
            DB::table('catalog_transfer_files')->where('run_id', $run)->update(['status' => 'pending', 'cursor' => 1]);
            $service->step($run);
            $this->assertSame(1, DB::table('catalog_transfer_rows')->where('run_id', $run)->count());
        }
    }

    public function test_conflicting_prices_are_not_applied_and_unmatched_rows_do_not_create_products(): void
    {
        $product = Product::factory()->create(['external_id' => '00123', 'price' => 500]);
        Http::fake(['svetofor-mebel.ru/*' => Http::response('<div id="content"><h1>Старый товар</h1></div>')]);
        $run = $this->runWithFile('Xlsx', [['00123', 56, 100], ['00123', 56, 200], ['UNKNOWN', 57, 10]]);
        $service = app(CatalogTransfer::class);
        for ($i = 0; $i < 5; $i++) {
            $service->step($run);
        }
        $this->assertSame(2, DB::table('catalog_transfer_rows')->where('status', 'conflict')->count());
        $this->assertDatabaseHas('catalog_transfer_rows', ['code' => 'UNKNOWN', 'status' => 'unmatched']);
        $this->assertEquals(500, $product->fresh()->price);
        $this->assertSame(1, Product::count());
        Http::assertSentCount(1);
    }

    public function test_parser_scopes_images_and_marks_categories_incomplete(): void
    {
        $parser = new OpenCartCardParser;
        $result = $parser->parse('<ul class="breadcrumb"><li><a href="https://svetofor-mebel.ru/index.php?route=product/category&amp;path=73_111">Прямые диваны</a></li></ul><div id="content"><h1>Диван</h1><ul class="thumbnails"><li><a href="/image/cache/catalog/test-1000x1000.jpg">Фото</a></li><li><a href="https://evil.test/image/test.jpg">Нет</a></li></ul><div class="related"><a href="/image/related.jpg">Другой товар</a></div></div>');
        $this->assertSame('Диван', $result['name']);
        $this->assertSame(['https://svetofor-mebel.ru/image/cache/catalog/test-1000x1000.jpg'], $result['images']);
        $this->assertSame('73_111', $result['category_trail'][0]['path']);
        $this->assertFalse($result['category_membership_complete']);
        $this->assertNull($parser->imageUrl('https://svetofor-mebel.ru/image/%2e%2e/private'));
        $this->assertNull($parser->imageUrl('http://svetofor-mebel.ru/image/a.jpg'));
    }

    public function test_source_respects_retry_after(): void
    {
        Http::fake(['svetofor-mebel.ru/*' => Http::response('', 429, ['Retry-After' => '60'])]);
        try {
            app(TransferSources::class)->card(56);
            $this->fail('Retry expected');
        } catch (TransferRetry $retry) {
            $this->assertSame(61, $retry->delay);
        }
    }

    public function test_source_does_not_follow_redirects(): void
    {
        Http::fake(['svetofor-mebel.ru/*' => Http::response('', 302, ['Location' => 'http://127.0.0.1'])]);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('HTTP 302');
        app(TransferSources::class)->card(56);
    }

    public function test_matching_variant_is_read_only_and_pause_stops_work(): void
    {
        $parent = Product::factory()->create();
        $variant = Product::factory()->create(['external_id' => '00123', 'parent_product_id' => $parent->id, 'price' => 700]);
        $run = $this->runWithFile();
        Http::fake(['svetofor-mebel.ru/*' => Http::response('<div id="content"><h1>Матрас</h1><ul class="thumbnails"><li><a href="/image/a.jpg">Фото</a></li></ul></div>')]);
        $service = app(CatalogTransfer::class);
        DB::table('catalog_transfer_runs')->where('id', $run)->update(['status' => 'paused']);
        $this->assertSame(-1, $service->step($run));
        $this->assertSame(0, DB::table('catalog_transfer_rows')->count());
        DB::table('catalog_transfer_runs')->where('id', $run)->update(['status' => 'running']);
        $service->step($run);
        $service->step($run);
        $row = DB::table('catalog_transfer_rows')->first();
        $this->assertSame('matched', $row->status);
        $this->assertSame($variant->id, json_decode($row->result, true)['product']['id']);
        $this->assertEquals(700, $variant->fresh()->price);
        $service->step($run);
        $this->assertDatabaseHas('catalog_transfer_runs', ['id' => $run, 'status' => 'review']);
    }

    public function test_admin_page_renders_and_has_pause_resume_but_no_apply_action(): void
    {
        Role::findOrCreate('super_admin', 'web');
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin_sv'));
        $id = $this->runWithFile();
        Livewire::test(CatalogTransferPage::class)->assertSuccessful()->assertSee('Таблица — источник истины')
            ->call('pause')->assertHasNoErrors();
        $this->assertDatabaseHas('catalog_transfer_runs', ['id' => $id, 'status' => 'paused']);
        \Illuminate\Support\Facades\Queue::fake();
        Livewire::test(CatalogTransferPage::class)->call('start')->assertHasNoErrors();
        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\AnalyzeCatalogTransfer::class);
        $this->assertFalse(method_exists(CatalogTransferPage::class, 'apply'));
    }

    public function test_guest_cannot_access_page(): void
    {
        $this->assertFalse(CatalogTransferPage::canAccess());
        Livewire::test(CatalogTransferPage::class)->assertForbidden();
    }

    public function test_only_progress_polls_and_stops_after_completion_or_pause(): void
    {
        Role::findOrCreate('super_admin', 'web');
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin_sv'));
        $id = $this->runWithFile();
        $this->runWithFile(); // The child must retain the selected run, not silently select the latest.
        $progress = Livewire::test(\App\Livewire\CatalogTransferProgress::class, ['runId' => $id]);
        $progress->assertSet('runId', $id)->assertSeeHtml('wire:poll.5s')
            ->assertDontSee('Товары и замечания')->assertDontSee('Загрузить и перенести товары');
        foreach (['paused', 'review', 'review_errors'] as $status) {
            DB::table('catalog_transfer_runs')->where('id', $id)->update(['status' => $status]);
            $progress->call('$refresh')->assertDontSeeHtml('wire:poll');
        }
        $template = file_get_contents(resource_path('views/filament/pages/catalog-transfer.blade.php'));
        $this->assertStringNotContainsString('wire:poll', $template);
        $this->assertStringContainsString('wire:target="uploads,createRun"', $template);
    }

    public function test_admin_uploads_file_and_repeated_start_does_not_duplicate_jobs(): void
    {
        Role::findOrCreate('super_admin', 'web');
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin_sv'));
        $this->runWithFile();
        $file = DB::table('catalog_transfer_files')->first();
        $upload = \Illuminate\Http\UploadedFile::fake()->createWithContent('catalog.xlsx', Storage::disk('local')->get($file->path));
        \Illuminate\Support\Facades\Queue::fake();
        $page = Livewire::test(CatalogTransferPage::class)->set('uploads', [$upload])->call('createRun')->assertHasNoErrors();
        $newRun = DB::table('catalog_transfer_runs')->latest('id')->first();
        $this->assertSame('running', $newRun->status);
        $this->assertEquals(1, $newRun->apply_enabled);
        $this->assertDatabaseHas('catalog_transfer_files', ['run_id' => $newRun->id, 'name' => 'catalog.xlsx']);
        $page->call('start')->call('start')->assertHasNoErrors();
        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\AnalyzeCatalogTransfer::class, 1);
    }

    public function test_api_token_is_encrypted_and_not_echoed(): void
    {
        Role::findOrCreate('super_admin', 'web');
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin_sv'));
        $token = str_repeat('secret-key-', 4);
        Livewire::test(CatalogTransferPage::class)->set('apiToken', $token)->call('saveApiToken')
            ->assertHasNoErrors()->assertSet('apiToken', '')->assertDontSee($token);
        $stored = DB::table('catalog_transfer_settings')->where('key', 'api_token')->value('value');
        $this->assertNotSame($token, $stored);
        $this->assertSame($token, app(\App\Services\Catalog\Transfer\TransferApiSettings::class)->config()['api_key']);
    }

    public function test_rows_without_price_do_not_bypass_conflicts_in_other_files(): void
    {
        Product::factory()->create(['external_id' => '00123']);
        $run = $this->runWithFile('Xlsx', [['00123', 56, 100], ['00123', 56, 200], ['00123', '', '']]);
        $service = app(CatalogTransfer::class);
        for ($i = 0; $i < 4; $i++) {
            $service->step($run);
        }
        $this->assertSame(3, DB::table('catalog_transfer_rows')->where('status', 'conflict')->count());
        Http::assertNothingSent();
    }

    public function test_formula_error_does_not_stop_other_rows(): void
    {
        $run = $this->runWithFile('Xlsx', [['=1+2', 56, 100], ['MISSING', 57, 200]]);
        Http::fake(['svetofor-mebel.ru/*' => Http::response('<div id="content"><h1>Товар</h1></div>')]);
        $service = app(CatalogTransfer::class);
        $service->step($run);
        $service->step($run);
        $this->assertDatabaseHas('catalog_transfer_rows', ['row_number' => 2, 'status' => 'error']);
        $this->assertDatabaseHas('catalog_transfer_rows', ['row_number' => 3, 'status' => 'unmatched']);
    }

    public function test_old_storefront_theme_gallery_is_supported(): void
    {
        $result = app(OpenCartCardParser::class)->parse('<div class="content-container"><div class="container"><h1>Вега-10</h1><div class="thumbnails"><div class="image-main"><a href="https://svetofor-mebel.ru/image/cache/catalog/vega-1000x1000.png">Фото</a></div></div></div></div>');
        $this->assertSame('Вега-10', $result['name']);
        $this->assertCount(1, $result['images']);
    }

    public function test_auxiliary_images_join_by_old_id_not_sheet_row(): void
    {
        $run = $this->runWithFile('Xlsx', [['00123', 56, 100]]);
        $file = DB::table('catalog_transfer_files')->where('run_id', $run)->first();
        $path = Storage::disk('local')->path($file->path);
        $book = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
        $book->createSheet()->setTitle('AdditionalImages')->fromArray([
            ['product_id', 'image', 'sort_order'],
            [9999, 'catalog/unrelated.jpg', 0],
            [56, 'catalog/correct.jpg', 0],
        ]);
        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();
        app(CatalogTransfer::class)->step($run);
        $source = json_decode(DB::table('catalog_transfer_rows')->where('run_id', $run)->value('source'), true);
        $this->assertSame(['https://svetofor-mebel.ru/image/catalog/correct.jpg'], $source['images']);
    }

    public function test_api_verifies_code_and_keeps_credentials_out_of_results(): void
    {
        config(['catalog_import.config.svetofor_1c.api_key' => 'test-secret', 'catalog_import.config.svetofor_1c.base_url' => 'https://api.example.test']);
        Http::fake(['api.example.test/*' => Http::response(['externalId' => '00123', 'payload' => ['name' => 'Товар'], 'price' => 500])]);
        $result = app(TransferSources::class)->api('00123');
        $this->assertSame('found', $result['status']);
        $this->assertStringNotContainsString('test-secret', json_encode($result));
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer test-secret') && str_ends_with($request->url(), '/00123'));
    }

    public function test_api_catalog_can_be_read_without_token(): void
    {
        config(['catalog_import.config.svetofor_1c.api_key' => '', 'catalog_import.config.svetofor_1c.base_url' => 'https://api.example.test']);
        Http::fake(['api.example.test/*' => Http::response(['externalId' => '00123', 'payload' => []])]);
        $this->assertSame('found', app(TransferSources::class)->api('00123')['status']);
        Http::assertSent(fn ($request) => ! $request->hasHeader('Authorization'));
    }

    public function test_category_candidates_require_parent_chain(): void
    {
        $root = \App\Models\Product\Category::factory()->create(['name' => 'Мягкая мебель']);
        $child = \App\Models\Product\Category::factory()->create(['name' => 'Прямые диваны', 'parent_id' => $root->id]);
        \App\Models\Product\Category::factory()->create(['name' => 'Прямые диваны']);
        $matches = app(\App\Services\Catalog\Transfer\TransferCategoryMatcher::class)->candidates([
            ['name' => 'МЯГКАЯ МЕБЕЛЬ'], ['name' => 'Прямые диваны'],
        ]);
        $this->assertSame([$child->id], array_column($matches, 'id'));
    }

    public function test_temporary_errors_are_bounded_and_logged(): void
    {
        Product::factory()->create(['external_id' => '00123']);
        $run = $this->runWithFile();
        $this->mock(TransferSources::class, function ($mock): void {
            $mock->shouldReceive('card')->times(5)->andThrow(new TransferRetry(61));
        });
        $service = app(CatalogTransfer::class);
        $service->step($run);
        for ($i = 0; $i < 5; $i++) {
            $delay = $service->step($run);
            $this->assertSame($i < 4 ? 61 : 1, $delay);
        }
        $this->assertDatabaseHas('catalog_transfer_rows', ['status' => 'error', 'attempts' => 5]);
        $this->assertSame(5, DB::table('catalog_transfer_events')->where('level', 'warning')->count());
    }

    public function test_analysis_records_full_api_preview_without_changing_product(): void
    {
        $product = Product::factory()->create(['sku' => '00123', 'price' => 500, 'stock' => 7]);
        $run = $this->runWithFile(data: [['00123', '', '900']]);
        $data = ['status' => 'found', 'external_id' => '00123', 'payload' => ['name' => 'API товар'],
            'stocks' => [['stockId' => 'W1', 'stockName' => 'Липецк', 'count' => 12]],
            'characteristics' => [['name' => 'Производитель', 'values' => ['Эра']]]];
        $this->mock(TransferSources::class)->shouldReceive('enrichment')->once()->with('00123')->andReturn($data);
        $service = app(CatalogTransfer::class);
        $service->step($run);
        $service->step($run);
        $row = DB::table('catalog_transfer_rows')->where('run_id', $run)->first();
        $this->assertSame($data, json_decode($row->result, true)['api']);
        $this->assertNull($row->api_applied_at);
        $this->assertEquals(500, $product->fresh()->price);
        $this->assertEquals(7, $product->fresh()->stock);
    }
}
