<?php

namespace Tests\Feature\Api;

use App\Models\Product\Product;
use App\Models\Product\ProductDocument;
use App\Services\Inventory\WarehouseStockResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductDocumentsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        WarehouseStockResolver::clearCache();
        Storage::fake(config('media-library.disk_name', 'public'));
    }

    public function test_product_card_lists_documents_in_operator_order(): void
    {
        $product = Product::factory()->create(['state' => 'active']);
        $this->attachDocument($product, 'Гарантийный талон', 'warranty.pdf', sortOrder: 2);
        $this->attachDocument($product, 'Инструкция по сборке', 'assembly-v3.pdf', sortOrder: 1);

        $documents = $this->getJson('/api/v1/products/' . $product->slug)
            ->assertOk()
            ->json('product.documents');

        $this->assertSame(['Инструкция по сборке', 'Гарантийный талон'], array_column($documents, 'title'));
        $this->assertSame('assembly-v3.pdf', $documents[0]['file_name']);
        $this->assertSame('application/pdf', $documents[0]['mime_type']);
        $this->assertSame('pdf', $documents[0]['extension']);
        $this->assertGreaterThan(0, $documents[0]['size']);
        $this->assertStringEndsWith('/assembly-v3.pdf', $documents[0]['url']);
    }

    public function test_hidden_documents_are_not_listed(): void
    {
        $product = Product::factory()->create(['state' => 'active']);
        $this->attachDocument($product, 'Черновик', 'draft.pdf')->update(['is_published' => false]);
        $this->attachDocument($product, 'Инструкция', 'manual.pdf');

        $documents = $this->getJson('/api/v1/products/' . $product->slug)
            ->assertOk()
            ->json('product.documents');

        $this->assertSame(['Инструкция'], array_column($documents, 'title'));
    }

    public function test_untitled_document_uses_file_name(): void
    {
        $product = Product::factory()->create(['state' => 'active']);
        $this->attachDocument($product, null, 'Сертификат соответствия.pdf');

        $this->getJson('/api/v1/products/' . $product->slug)
            ->assertOk()
            ->assertJsonPath('product.documents.0.title', 'Сертификат соответствия');
    }

    public function test_product_without_documents_returns_empty_list(): void
    {
        $product = Product::factory()->create(['state' => 'active']);
        ProductDocument::query()->create(['product_id' => $product->id, 'title' => 'Без файла']);

        $this->getJson('/api/v1/products/' . $product->slug)
            ->assertOk()
            ->assertJsonPath('product.documents', []);
    }

    public function test_deleting_product_removes_document_files(): void
    {
        $product = Product::factory()->create(['state' => 'active']);
        $document = $this->attachDocument($product, 'Инструкция', 'manual.pdf');
        $path = $document->file()->getPathRelativeToRoot();
        $disk = Storage::disk(config('media-library.disk_name', 'public'));
        $disk->assertExists($path);

        $product->delete();

        $this->assertDatabaseMissing('product_documents', ['id' => $document->id]);
        $disk->assertMissing($path);
    }

    private function attachDocument(Product $product, ?string $title, string $fileName, int $sortOrder = 0): ProductDocument
    {
        $document = ProductDocument::query()->create([
            'product_id' => $product->id,
            'title' => $title,
            'sort_order' => $sortOrder,
        ]);

        $document
            ->addMedia(UploadedFile::fake()->createWithContent($fileName, "%PDF-1.4\n%%EOF\n"))
            ->toMediaCollection(ProductDocument::MEDIA_COLLECTION);

        return $document;
    }
}
