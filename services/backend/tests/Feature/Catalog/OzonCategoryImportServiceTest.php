<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use App\Jobs\SyncOzonProductImagesJob;
use App\Models\Product\Category;
use App\Models\Product\Product;
use App\Services\Catalog\Integrations\Ozon\OzonCategoryImportService;
use Illuminate\Support\Facades\Queue;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Tests\TestCase;

class OzonCategoryImportServiceTest extends TestCase
{
    /** @var list<string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    public function test_it_creates_product_by_1c_code_and_attaches_it_to_category(): void
    {
        Queue::fake();
        $category = Category::factory()->create();
        $file = $this->makeOzonFile([
            [
                'Артикул*' => '1001',
                'Название товара' => 'Тестовый стол',
                'Предельная цена без акций, руб.*' => 15990,
                'Зачёркнутая цена, руб.' => 17990,
                'Штрихкод (Серийный номер / EAN)' => 'OZN1001',
                'Вес в упаковке, г*' => 12500,
                'Ширина упаковки, мм*' => 800,
                'Высота упаковки, мм*' => 120,
                'Длина упаковки, мм*' => 1400,
                'Ссылка на главное фото*' => 'https://cdn1.ozone.ru/test/1001.jpg',
                'Материал корпуса' => 'ЛДСП',
            ],
        ]);

        $result = app(OzonCategoryImportService::class)->import($file, $category);

        $product = Product::query()->where('external_id', '1001')->firstOrFail();
        $this->assertSame('Тестовый стол', $product->name);
        $this->assertSame('OZN1001', $product->gtin);
        $this->assertEquals(15990.0, (float) $product->price);
        $this->assertEquals(12.5, (float) $product->weight);
        $this->assertEquals(80.0, (float) $product->width);
        $this->assertEquals(12.0, (float) $product->height);
        $this->assertEquals(140.0, (float) $product->length);
        $this->assertSame((string) $product->id, (string) $product->sku);
        $this->assertTrue($product->taxons()->whereKey($category->id)->exists());
        $this->assertSame(1, $result['created_products']);
        Queue::assertPushed(SyncOzonProductImagesJob::class, fn ($job) => $job->productId === $product->id);
    }

    public function test_reimport_updates_existing_product_without_creating_duplicate_and_adds_target_category(): void
    {
        Queue::fake();
        $oldCategory = Category::factory()->create();
        $targetCategory = Category::factory()->create();
        $product = Product::factory()->create([
            'external_id' => '2001',
            'name' => 'Старое название',
            'price' => 1000,
        ]);
        $product->taxons()->sync([$oldCategory->id]);

        $file = $this->makeOzonFile([
            [
                'Артикул*' => '2001',
                'Название товара' => 'Новое название из Ozon',
                'Предельная цена без акций, руб.*' => 2500,
            ],
        ]);

        $result = app(OzonCategoryImportService::class)->import($file, $targetCategory);

        $this->assertSame(1, Product::query()->where('external_id', '2001')->count());
        $product->refresh();
        $this->assertSame('Новое название из Ozon', $product->name);
        $this->assertEquals(2500.0, (float) $product->price);
        $this->assertTrue($product->taxons()->whereKey($oldCategory->id)->exists());
        $this->assertTrue($product->taxons()->whereKey($targetCategory->id)->exists());
        $this->assertSame(0, $result['created_products']);
        $this->assertSame(1, $result['updated_products']);
    }

    public function test_same_ozon_model_creates_technical_parent_and_keeps_1c_codes_on_variants(): void
    {
        Queue::fake();
        $category = Category::factory()->create();
        $file = $this->makeOzonFile([
            [
                'Артикул*' => '3001',
                'Название товара' => 'Комплект тумб 450x400, Серый',
                'Предельная цена без акций, руб.*' => 10000,
                'Название модели (для объединения в одну карточку)*' => 'Комплект тумб X',
                'Цвет товара' => 'серый',
                'Название цвета' => 'Серый',
            ],
            [
                'Артикул*' => '3002',
                'Название товара' => 'Комплект тумб 450x400, Бежевый',
                'Предельная цена без акций, руб.*' => 11000,
                'Название модели (для объединения в одну карточку)*' => 'Комплект тумб X',
                'Цвет товара' => 'бежевый',
                'Название цвета' => 'Бежевый',
            ],
        ]);

        $result = app(OzonCategoryImportService::class)->import($file, $category);

        $variants = Product::query()->whereIn('external_id', ['3001', '3002'])->orderBy('external_id')->get();
        $this->assertCount(2, $variants);
        $this->assertNotNull($variants[0]->parent_product_id);
        $this->assertSame($variants[0]->parent_product_id, $variants[1]->parent_product_id);

        $parent = Product::query()->findOrFail($variants[0]->parent_product_id);
        $this->assertTrue($parent->isVariable());
        $this->assertNull($parent->external_id);
        $this->assertNull($parent->sku);
        $this->assertNotNull($parent->ozon_group_key);
        $this->assertTrue($parent->taxons()->whereKey($category->id)->exists());
        $this->assertSame(['3001', '3002'], $variants->pluck('external_id')->all());
        $this->assertSame(1, $result['created_parents']);
        $this->assertSame(2, $result['created_variants']);
        $this->assertGreaterThanOrEqual(
            2,
            \DB::table('product_variant_attributes')->whereIn('product_id', $variants->pluck('id'))->count(),
        );
    }

    public function test_existing_simple_products_are_reused_when_ozon_groups_them(): void
    {
        Queue::fake();
        $category = Category::factory()->create();
        $first = Product::factory()->create(['external_id' => '4001']);
        $second = Product::factory()->create(['external_id' => '4002']);

        $file = $this->makeOzonFile([
            [
                'Артикул*' => '4001',
                'Название товара' => 'Кресло Луна серое',
                'Название модели (для объединения в одну карточку)*' => 'Кресло Луна',
            ],
            [
                'Артикул*' => '4002',
                'Название товара' => 'Кресло Луна синее',
                'Название модели (для объединения в одну карточку)*' => 'Кресло Луна',
            ],
        ]);

        app(OzonCategoryImportService::class)->import($file, $category);

        $this->assertSame(2, Product::query()->whereIn('external_id', ['4001', '4002'])->count());
        $first->refresh();
        $second->refresh();
        $this->assertNotNull($first->parent_product_id);
        $this->assertSame($first->parent_product_id, $second->parent_product_id);
    }

    public function test_existing_variable_group_can_be_attached_to_another_category_without_new_parent(): void
    {
        Queue::fake();
        $firstCategory = Category::factory()->create();
        $secondCategory = Category::factory()->create();
        $file = $this->makeOzonFile([
            [
                'Артикул*' => '4501',
                'Название товара' => 'Стул Флора серый',
                'Название модели (для объединения в одну карточку)*' => 'Стул Флора',
                'Название цвета' => 'Серый',
            ],
            [
                'Артикул*' => '4502',
                'Название товара' => 'Стул Флора бежевый',
                'Название модели (для объединения в одну карточку)*' => 'Стул Флора',
                'Название цвета' => 'Бежевый',
            ],
        ]);

        app(OzonCategoryImportService::class)->import($file, $firstCategory);
        $firstVariant = Product::query()->where('external_id', '4501')->firstOrFail();
        $parentId = (int) $firstVariant->parent_product_id;

        app(OzonCategoryImportService::class)->import($file, $secondCategory);

        $firstVariant->refresh();
        $parent = Product::query()->findOrFail($parentId);
        $this->assertSame($parentId, (int) $firstVariant->parent_product_id);
        $this->assertSame(1, Product::query()->where('ozon_group_key', $parent->ozon_group_key)->count());
        $this->assertTrue($parent->taxons()->whereKey($firstCategory->id)->exists());
        $this->assertTrue($parent->taxons()->whereKey($secondCategory->id)->exists());
        $this->assertTrue($firstVariant->taxons()->whereKey($secondCategory->id)->exists());
    }

    public function test_duplicate_1c_code_inside_file_blocks_import(): void
    {
        $category = Category::factory()->create();
        $file = $this->makeOzonFile([
            ['Артикул*' => '5001', 'Название товара' => 'Товар A'],
            ['Артикул*' => '5001', 'Название товара' => 'Товар B'],
        ]);

        $preview = app(OzonCategoryImportService::class)->preview($file, $category);
        $this->assertNotEmpty($preview['errors']);

        $this->expectException(RuntimeException::class);
        app(OzonCategoryImportService::class)->import($file, $category);
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private function makeOzonFile(array $rows): string
    {
        $headers = [
            '№',
            'Артикул*',
            'Название товара',
            'Предельная цена без акций, руб.*',
            'Зачёркнутая цена, руб.',
            'SKU',
            'Штрихкод (Серийный номер / EAN)',
            'Вес в упаковке, г*',
            'Ширина упаковки, мм*',
            'Высота упаковки, мм*',
            'Длина упаковки, мм*',
            'Ссылка на главное фото*',
            'Ссылки на дополнительные фото',
            'Бренд*',
            'Название модели (для объединения в одну карточку)*',
            'Цвет товара',
            'Название цвета',
            'Тип*',
            'Аннотация',
            'Материал корпуса',
            'Ошибка',
            'Недочёты',
        ];

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Шаблон');
        $sheet->fromArray($headers, null, 'A2');
        $sheet->setCellValue('B3', 'Обязательное поле');
        $sheet->setCellValue('B4', 'Введите артикул товара или его номер в вашей базе');
        $sheet->setCellValue('C4', 'Подсказка Ozon');

        $rowNumber = 5;
        foreach ($rows as $index => $row) {
            $values = [];
            foreach ($headers as $header) {
                if ($header === '№') {
                    $values[] = $index + 1;
                    continue;
                }
                $values[] = $row[$header] ?? null;
            }
            $sheet->fromArray($values, null, 'A' . $rowNumber++);
        }

        $path = tempnam(sys_get_temp_dir(), 'ozon-test-') . '.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();
        $this->tempFiles[] = $path;

        return $path;
    }
}
