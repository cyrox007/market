<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Products\Pages\EditProduct;
use App\Models\Product\Attribute;
use App\Models\Product\Product;
use App\Models\Product\ProductDocument;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProductDocumentsFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Attribute::ensureVariantAttribute();
        Storage::fake(config('media-library.disk_name', 'public'));

        Role::findOrCreate('super_admin', 'web');
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        Filament::setCurrentPanel(Filament::getPanel('admin_sv'));
    }

    public function test_operator_attaches_documents_in_product_card(): void
    {
        $product = Product::factory()->create(['state' => 'active']);
        $undoRepeaterFake = Repeater::fake();

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->fillForm([
                'documents' => [
                    ['title' => 'Инструкция по сборке', 'file' => [$this->pdf('assembly.pdf')], 'is_published' => true],
                    ['title' => null, 'file' => [$this->pdf('certificate.pdf')], 'is_published' => false],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $undoRepeaterFake();

        $documents = $product->documents()->with('media')->get();
        $this->assertCount(2, $documents);
        $this->assertSame('Инструкция по сборке', $documents[0]->title);
        $this->assertSame('assembly.pdf', $documents[0]->file()?->file_name);
        $this->assertTrue($documents[0]->is_published);
        $this->assertNull($documents[1]->title);
        $this->assertFalse($documents[1]->is_published);
        $this->assertSame('certificate.pdf', $documents[1]->file()?->file_name);
        $this->assertSame([1, 2], $documents->pluck('sort_order')->all());
        $this->assertSame(2, ProductDocument::count());
    }

    private function pdf(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n%%EOF\n");
    }
}
