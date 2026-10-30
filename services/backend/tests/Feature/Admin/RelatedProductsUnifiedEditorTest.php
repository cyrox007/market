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
                'enable_recommendations' => true,
            ])
            ->callMountedAction()->assertHasNoActionErrors();

        $this->assertTrue((bool) $collection->fresh()->recommend_collection_members);
        $this->assertTrue($collection->products()->where('products.id', $wardrobe->id)->exists());
        $this->getJson('/api/v1/products/'.$wardrobe->id.'/related')
            ->assertOk()->assertJsonPath('data.0.id', $mezzanine->id);

        // Start a fresh Livewire request for the next modal operation.
        $page = Livewire::test(RelatedProductsRelationManager::class, [
            'ownerRecord' => $wardrobe->fresh(), 'pageClass' => EditProduct::class,
        ]);
        $page->mountTableAction('manageSeriesRecommendations')
            ->setActionData([
                'collection_id' => $collection->id,
                'operation' => 'disable',
            ])
            ->callMountedAction()->assertHasNoActionErrors();
        $this->assertFalse((bool) $collection->fresh()->recommend_collection_members);
        $this->assertTrue($collection->products()->where('products.id', $wardrobe->id)->exists());
    }
}
