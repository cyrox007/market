<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\RelationManagers\RelatedProductsRelationManager;
use App\Models\Product\Product;
use App\Models\Product\ProductCollection;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RelatedProductsUnifiedEditorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('super_admin', 'web');
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin_sv'));
    }

    private function series(Product ...$items): ProductCollection
    {
        $collection = ProductCollection::create([
            'name' => 'Бруно', 'slug' => 'bruno-unified-test',
            'is_auto' => false, 'scope_type' => null,
            'is_active' => true, 'recommend_collection_members' => true,
            'priority' => 0, 'limit' => 12,
        ]);
        foreach ($items as $index => $item) {
            $collection->products()->attach($item->id, ['sort_order' => $index]);
        }
        $collection->flushHomeCaches();
        return $collection;
    }

    public function test_existing_related_products_section_displays_manual_and_series_sources(): void
    {
        $wardrobe = Product::factory()->create(['name' => 'Шкаф Бруно', 'state' => 'active']);
        $mezzanine = Product::factory()->create(['name' => 'Антресоль Бруно', 'state' => 'active']);
        $commode = Product::factory()->create(['name' => 'Комод', 'state' => 'active']);
        $this->series($wardrobe, $mezzanine);
        $wardrobe->attachRelatedProduct($commode);

        Livewire::test(RelatedProductsRelationManager::class, [
            'ownerRecord' => $wardrobe, 'pageClass' => EditProduct::class,
        ])
            ->assertSee('Управлять сериями')
            ->assertSee('Что сейчас показывается покупателю')
            ->assertSee('Вручную')
            ->assertSee('Серия: Бруно')
            ->assertSee('Антресоль Бруно')
            ->assertSee('Комод');
    }

    public function test_series_membership_and_global_recommendation_switch_live_in_related_section(): void
    {
        $wardrobe = Product::factory()->create(['name' => 'Шкаф Бруно', 'state' => 'active']);
        $mezzanine = Product::factory()->create(['name' => 'Антресоль Бруно', 'state' => 'active']);
        $collection = $this->series($mezzanine);
        $collection->forceFill(['recommend_collection_members' => false])->save();

        $page = Livewire::test(RelatedProductsRelationManager::class, [
            'ownerRecord' => $wardrobe, 'pageClass' => EditProduct::class,
        ]);

        $page->mountTableAction('manageSeriesRecommendations')
            ->setActionData([
                'collection_id' => $collection->id,
                'operation' => 'add',
            ])
            ->callMountedAction()->assertHasNoActionErrors();

        $this->assertTrue((bool) $collection->fresh()->recommend_collection_members);
        $this->assertTrue($collection->products()->where('products.id', $wardrobe->id)->exists());
        $this->getJson('/api/v1/products/'.$wardrobe->id.'/related')
            ->assertOk()->assertJsonPath('data.0.id', $mezzanine->id);

    }

    public function test_series_recommendations_can_be_disabled_from_existing_related_section(): void
    {
        $wardrobe = Product::factory()->create(['name' => 'Шкаф Бруно', 'state' => 'active']);
        $mezzanine = Product::factory()->create(['name' => 'Антресоль Бруно', 'state' => 'active']);
        $collection = $this->series($wardrobe, $mezzanine);

        Livewire::test(RelatedProductsRelationManager::class, [
            'ownerRecord' => $wardrobe, 'pageClass' => EditProduct::class,
        ])->mountTableAction('manageSeriesRecommendations')
            ->setActionData([
                'collection_id' => $collection->id,
                'operation' => 'disable',
            ])
            ->callMountedAction()->assertHasNoActionErrors();

        $this->assertFalse((bool) $collection->fresh()->recommend_collection_members);
        $this->assertTrue($collection->products()->where('products.id', $wardrobe->id)->exists());
    }

    public function test_fallback_recommendations_appear_as_real_rows_instead_of_empty_manual_table(): void
    {
        $owner = Product::factory()->create(['name' => 'Шкаф без рекомендаций', 'state' => 'active']);
        $other = Product::factory()->create(['name' => 'Комод рекомендованный', 'state' => 'active']);

        Livewire::test(RelatedProductsRelationManager::class, [
            'ownerRecord' => $owner, 'pageClass' => EditProduct::class,
        ])
            ->assertCanSeeTableRecords([$other])
            ->assertSee('Автоподбор')
            ->assertSee('На сайте')
            ->assertSee('Массово добавить');
    }

    public function test_bulk_add_filters_by_manufacturer_and_creates_manual_links(): void
    {
        $owner = Product::factory()->create(['name' => 'Шкаф Бруно', 'state' => 'active']);
        $target = Product::factory()->create(['name' => 'Антресоль Бруно', 'state' => 'active']);
        $other = Product::factory()->create(['name' => 'Комод Бруно', 'state' => 'active']);
        app(\App\Services\Product\ProductManufacturer::class)->save($target, 'Фабрика Север');
        app(\App\Services\Product\ProductManufacturer::class)->save($other, 'Фабрика Юг');

        Livewire::test(RelatedProductsRelationManager::class, [
            'ownerRecord' => $owner, 'pageClass' => EditProduct::class,
        ])
            ->mountTableAction('bulkAddRelated')
            ->setActionData([
                'operation' => 'manual',
                'manufacturer_name' => 'Фабрика Север',
                'product_search' => 'Бруно',
                'target_product_ids' => [(string) $target->id],
            ])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('product_related_products', [
            'product_id' => $owner->id, 'related_product_id' => $target->id,
        ]);
        $this->assertDatabaseHas('product_related_products', [
            'product_id' => $target->id, 'related_product_id' => $owner->id,
        ]);
        $this->assertDatabaseMissing('product_related_products', [
            'product_id' => $owner->id, 'related_product_id' => $other->id,
        ]);
    }

    public function test_bulk_add_to_series_keeps_existing_manual_recommendations(): void
    {
        $owner = Product::factory()->create(['name' => 'Шкаф Бруно', 'state' => 'active']);
        $mezzanine = Product::factory()->create(['name' => 'Антресоль Бруно', 'state' => 'active']);
        $manual = Product::factory()->create(['name' => 'Комод вне серии', 'state' => 'active']);
        $collection = $this->series();
        $owner->attachRelatedProduct($manual);

        Livewire::test(RelatedProductsRelationManager::class, [
            'ownerRecord' => $owner, 'pageClass' => EditProduct::class,
        ])
            ->mountTableAction('bulkAddRelated')
            ->setActionData([
                'operation' => 'series',
                'collection_id' => $collection->id,
                'target_product_ids' => [(string) $mezzanine->id],
            ])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertTrue($collection->products()->where('products.id', $owner->id)->exists());
        $this->assertTrue($collection->products()->where('products.id', $mezzanine->id)->exists());
        $this->assertSame([$manual->id, $mezzanine->id],
            collect($this->getJson('/api/v1/products/'.$owner->id.'/related')
                ->assertOk()->json('data'))->pluck('id')->all());
    }
}
