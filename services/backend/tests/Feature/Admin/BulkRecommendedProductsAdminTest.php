<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Products\Pages\ListProducts;
use App\Models\Product\Product;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BulkRecommendedProductsAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('super_admin', 'web');
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin_sv'));
    }

    public function test_product_manager_mass_action_creates_symmetric_links_for_selected_cards(): void
    {
        $wardrobe = Product::factory()->create(['name' => 'Шкаф Бруно']);
        $mezzanine = Product::factory()->create(['name' => 'Антресоль Бруно']);
        $commode = Product::factory()->create(['name' => 'Комод Бруно']);

        Livewire::test(ListProducts::class)
            ->callTableBulkAction('bulk_related_products', [$wardrobe, $mezzanine], data: [
                'operation' => 'add', 'target_product_ids' => [(string) $commode->id],
            ])
            ->assertHasNoTableBulkActionErrors();

        $this->assertDatabaseHas('product_related_products', [
            'product_id' => $wardrobe->id, 'related_product_id' => $commode->id,
        ]);
        $this->assertDatabaseHas('product_related_products', [
            'product_id' => $commode->id, 'related_product_id' => $wardrobe->id,
        ]);
        $this->assertDatabaseHas('product_related_products', [
            'product_id' => $mezzanine->id, 'related_product_id' => $commode->id,
        ]);

        $related = collect($this->getJson('/api/v1/products/'.$wardrobe->id.'/related')
            ->assertOk()->json('data'))->pluck('id')->all();
        $this->assertContains($commode->id, $related);
    }

    public function test_manager_replace_action_removes_previous_pairs_not_other_links(): void
    {
        [$wardrobe, $mezzanine, $commode, $desk] = Product::factory()->count(4)->create()->all();
        $wardrobe->attachRelatedProduct($commode);
        $commode->attachRelatedProduct($desk);

        Livewire::test(ListProducts::class)
            ->callTableBulkAction('bulk_related_products', [$wardrobe], data: [
                'operation' => 'replace',
                'target_product_ids' => [(string) $mezzanine->id],
            ])
            ->assertHasNoTableBulkActionErrors();

        $this->assertDatabaseMissing('product_related_products', [
            'product_id' => $wardrobe->id, 'related_product_id' => $commode->id,
        ]);
        $this->assertDatabaseHas('product_related_products', [
            'product_id' => $wardrobe->id, 'related_product_id' => $mezzanine->id,
        ]);
        $this->assertDatabaseHas('product_related_products', [
            'product_id' => $commode->id, 'related_product_id' => $desk->id,
        ]);
    }
}
