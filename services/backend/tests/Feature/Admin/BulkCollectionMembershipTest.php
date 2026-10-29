<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Products\Pages\ListProducts;
use App\Models\Product\Product;
use App\Models\Product\ProductCollection;
use App\Models\User;
use App\Services\Catalog\BulkCollectionMembership;
use Filament\Facades\Filament;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BulkCollectionMembershipTest extends TestCase
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

    private function bruno(): ProductCollection
    {
        return ProductCollection::create([
            'name' => 'Бруно', 'slug' => 'bruno',
            'is_auto' => false, 'scope_type' => null,
            'is_active' => true, 'recommend_collection_members' => false,
            'priority' => 0, 'limit' => 12,
        ]);
    }

    public function test_mass_action_assigns_explicit_series_and_enables_mutual_recommendations(): void
    {
        $series = $this->bruno();
        $wardrobe = Product::factory()->create(['name' => 'Шкаф Бруно', 'state' => 'active']);
        $mezzanine = Product::factory()->create(['name' => 'Антресоль Бруно', 'state' => 'active']);
        $unrelated = Product::factory()->create(['name' => 'Шкаф другой коллекции', 'state' => 'active']);

        Livewire::test(ListProducts::class)->callTableBulkAction('bulk_collection_membership', [
            $wardrobe, $mezzanine,
        ], data: [
            'collection_id' => $series->id, 'operation' => 'add', 'enable_recommendations' => true,
        ])->assertHasNoTableBulkActionErrors();

        $this->assertDatabaseHas('product_product_collection', [
            'product_collection_id' => $series->id, 'product_id' => $wardrobe->id,
        ]);
        $this->assertDatabaseHas('product_product_collection', [
            'product_collection_id' => $series->id, 'product_id' => $mezzanine->id,
        ]);
        $this->assertTrue((bool) $series->fresh()->recommend_collection_members);

        $related = collect($this->getJson('/api/v1/products/'.$wardrobe->id.'/related')
            ->assertOk()->json('data'))->pluck('id')->all();
        $this->assertSame([$mezzanine->id], $related);
        $this->assertNotContains($unrelated->id, $related);
    }

    public function test_add_is_idempotent_remove_does_not_delete_other_members_and_new_members_are_live(): void
    {
        $series = $this->bruno();
        [$wardrobe, $mezzanine, $desk] = Product::factory()->count(3)->create([
            'state' => 'active',
        ])->all();
        $manager = app(BulkCollectionMembership::class);
        $manager->apply($series->id, [$wardrobe->id, $mezzanine->id], 'add', true);
        $this->assertSame([$mezzanine->id], collect($this->getJson('/api/v1/products/'.$wardrobe->id.'/related')
            ->assertOk()->json('data'))->pluck('id')->all());

        $repeat = $manager->apply($series->id, [$wardrobe->id, $mezzanine->id], 'add', true);
        $this->assertSame(0, $repeat['added']);
        $manager->apply($series->id, [$desk->id], 'add');
        $this->assertContains($desk->id, collect($this->getJson('/api/v1/products/'.$wardrobe->id.'/related')
            ->assertOk()->json('data'))->pluck('id')->all());

        $manager->apply($series->id, [$mezzanine->id], 'remove');
        $this->assertSame([$desk->id], collect($this->getJson('/api/v1/products/'.$wardrobe->id.'/related')
            ->assertOk()->json('data'))->pluck('id')->all());
        $this->assertFalse($series->products()->where('products.id', $mezzanine->id)->exists());
    }

    public function test_bulk_assignment_rejects_variants_and_system_collections(): void
    {
        $series = $this->bruno();
        $parent = Product::factory()->create();
        $variant = Product::factory()->create(['parent_product_id' => $parent->id]);

        try {
            app(BulkCollectionMembership::class)->apply($series->id, [$variant->id], 'add');
            $this->fail('Variant may not be a standalone recommendation');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('collection_id', $exception->errors());
        }
        $series->update(['is_auto' => true, 'scope_type' => 'featured']);
        try {
            app(BulkCollectionMembership::class)->apply($series->id, [$parent->id], 'add');
            $this->fail('Auto collections are not editorial furniture series');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('collection_id', $exception->errors());
        }
        $this->assertSame(0, $series->products()->count());
    }
}
