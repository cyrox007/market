<?php

namespace Tests\Feature\Admin;

use App\Filament\Widgets\CatalogCardAttentionWidget;
use App\Models\CatalogCardAuditRun;
use App\Models\CatalogCardIssue;
use App\Models\Product\Category;
use App\Models\Product\Color\ProductColorRow;
use App\Models\Product\Product;
use App\Services\Catalog\CatalogCardQuality;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use App\Models\User;
use Tests\TestCase;

class CatalogCardAttentionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin_sv'));
    }

    private function codes(Product $product, bool $files = false): array
    {
        return array_column(app(CatalogCardQuality::class)->inspect($product, [], $files), 'code');
    }

    private function mainImage(Product $product, bool $originalExists = false): Media
    {
        $media = new Media();
        $media->model_type = $product->getMorphClass();
        $media->model_id = $product->id;
        $media->collection_name = 'images';
        $media->name = 'main';
        $media->file_name = 'main.jpg';
        $media->mime_type = 'image/jpeg';
        $media->disk = 'public';
        $media->conversions_disk = 'public';
        $media->size = 3;
        $media->manipulations = [];
        $media->custom_properties = [];
        $media->generated_conversions = [];
        $media->responsive_images = [];
        $media->save();
        if ($originalExists) {
            Storage::disk('public')->put($media->getPathRelativeToRoot(), 'abc');
        }

        return $media;
    }

    public function test_variable_parent_uses_active_offer_image_and_does_not_need_its_own_price_or_external_id(): void
    {
        $parent = Product::factory()->create([
            'is_variable' => true, 'external_id' => null, 'price' => 0, 'description' => 'Описан',
        ]);
        $offer = Product::factory()->create([
            'parent_product_id' => $parent->id,
            'price' => 1500,
            'external_id' => 'PARENT-OFFER-1',
        ]);
        $this->mainImage($offer);
        $codes = $this->codes($parent->fresh());

        $this->assertNotContains('no_image', $codes);
        $this->assertNotContains('no_price', $codes);
        $this->assertNotContains('no_external_id', $codes);
        $this->assertNotContains('no_active_offers', $codes);
    }

    public function test_offer_inherits_photo_category_and_description_but_not_external_id(): void
    {
        $parent = Product::factory()->create([
            'is_variable' => true, 'description' => '<p>Описание родителя</p>',
        ]);
        $category = Category::factory()->create();
        $parent->taxons()->attach($category->id);
        $this->mainImage($parent);
        $offer = Product::factory()->create([
            'parent_product_id' => $parent->id,
            'description' => null,
            'external_id' => null,
            'price' => 2500,
        ]);

        $codes = $this->codes($offer->fresh());
        $this->assertNotContains('no_image', $codes);
        $this->assertNotContains('no_category', $codes);
        $this->assertNotContains('no_description', $codes);
        $this->assertContains('no_external_id', $codes);
    }

    public function test_missing_price_and_main_image_are_critical_even_with_positive_stock(): void
    {
        $product = Product::factory()->create([
            'price' => 0, 'stock' => 100, 'external_id' => 'A001',
        ]);
        $reasons = app(CatalogCardQuality::class)->inspect($product, [], false);
        $critical = array_column(array_filter($reasons, fn ($reason) => $reason['severity'] === 'critical'), 'code');

        $this->assertContains('no_price', $critical);
        $this->assertContains('no_image', $critical);
    }

    public function test_variable_parent_without_active_offers_is_critical(): void
    {
        $parent = Product::factory()->create(['is_variable' => true]);
        Product::factory()->create(['parent_product_id' => $parent->id, 'state' => 'inactive']);
        $this->assertContains('no_active_offers', $this->codes($parent->fresh()));
    }

    public function test_empty_color_row_is_a_specific_warning(): void
    {
        $product = Product::factory()->create();
        ProductColorRow::query()->create([
            'product_id' => $product->id, 'color_element_id' => null,
            'color_solution_id' => null, 'sort_order' => 0,
        ]);
        $this->assertContains('incomplete_colors', $this->codes($product->fresh()));
    }

    public function test_existing_original_with_missing_thumbnail_is_warning_only_after_processing_grace_period(): void
    {
        Storage::fake('public');
        $product = Product::factory()->create();
        $media = $this->mainImage($product, true);
        $recent = $this->codes($product->fresh(), true);
        $this->assertNotContains('missing_thumb', $recent);
        $this->assertNotContains('no_image', $recent);

        $media->forceFill(['created_at' => now()->subHour()])->saveQuietly();
        $old = $this->codes($product->fresh(), true);
        $this->assertContains('missing_thumb', $old);
        $this->assertNotContains('no_image', $old);

        Storage::disk('public')->delete($media->getPathRelativeToRoot());
        $this->assertContains('no_image', $this->codes($product->fresh(), true));
    }

    public function test_inactive_products_do_not_trigger_diagnostics(): void
    {
        $product = Product::factory()->create(['state' => 'inactive', 'price' => 0]);
        $this->assertSame([], app(CatalogCardQuality::class)->inspect($product, [], false));
    }

    public function test_command_requires_one_critical_or_two_warnings_and_keeps_only_completed_results(): void
    {
        $critical = Product::factory()->create([
            'price' => 0, 'external_id' => 'CRIT-1', 'description' => 'Есть описание',
        ]);
        $warnings = Product::factory()->create([
            'price' => 1500, 'external_id' => null, 'description' => null,
        ]);
        $this->mainImage($warnings);
        $oneWarning = Product::factory()->create([
            'price' => 2500, 'external_id' => null, 'description' => 'Заполнено',
        ]);
        $category = Category::factory()->create();
        $oneWarning->taxons()->attach($category->id);
        $this->mainImage($oneWarning);

        $this->assertSame(0, Artisan::call('catalog:audit-cards', ['--skip-files' => true]));
        $run = CatalogCardAuditRun::latestCompleted();
        $this->assertNotNull($run);
        $this->assertNull($run->files_checked_at);
        $this->assertSame(3, $run->checked_count);
        $this->assertSame(2, $run->affected_count);
        $this->assertSame(2, CatalogCardIssue::query()->fromLatestCompleted()->count());
        $this->assertTrue(CatalogCardIssue::query()->where('product_id', $critical->id)->exists());
        $this->assertTrue(CatalogCardIssue::query()->where('product_id', $warnings->id)->exists());
        $this->assertFalse(CatalogCardIssue::query()->where('product_id', $oneWarning->id)->exists());

        $critical->update(['price' => 1500]);
        $this->assertSame(0, Artisan::call('catalog:audit-cards', ['--skip-files' => true]));
        $this->assertSame(1, CatalogCardAuditRun::query()->count());
        $this->assertSame(2, CatalogCardIssue::query()->count());
    }

    public function test_product_viewers_see_dated_results_while_unprivileged_users_cannot(): void
    {
        Permission::findOrCreate('viewAny products', 'web');
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->assertFalse(CatalogCardAttentionWidget::canView());

        $user->givePermissionTo('viewAny products');
        $this->actingAs($user);
        $this->assertTrue(CatalogCardAttentionWidget::canView());
        Livewire::test(CatalogCardAttentionWidget::class)
            ->assertSuccessful()
            ->assertSee('Аудит ещё не завершался');

        Product::factory()->create(['price' => 0]);
        $this->assertSame(0, Artisan::call('catalog:audit-cards', ['--skip-files' => true]));
        Livewire::test(CatalogCardAttentionWidget::class)
            ->assertSuccessful()
            ->assertSee('Карточки требуют внимания')
            ->assertSee('Отсутствует положительная цена');
    }
}
