<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\RelationManagers\ProductVariantsRelationManager;
use App\Models\Product\Product;
use App\Models\User;
use App\Services\Product\SellableBundle;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BundleCompositionFormTest extends TestCase
{
    public function test_modal_preloads_group_skus_preserves_selection_and_saves_optional_price(): void
    {
        Role::findOrCreate('super_admin', 'web');
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin_sv'));
        $root = Product::factory()->create(['is_variable' => true, 'is_sellable_bundle' => true]);
        $group = Product::factory()->create(['is_variable' => true]);
        $sku = Product::factory()->create(['parent_product_id' => $group->id, 'price' => 1500]);
        $other = Product::factory()->create(['parent_product_id' => $group->id, 'price' => 1700]);
        $offer = Product::factory()->create(['parent_product_id' => $root->id]);
        $root->sellableBundleGroups()->attach($group->id);
        app(SellableBundle::class)->saveOfferComposition($offer, [
            ['group_id' => $group->id, 'product_id' => $sku->id, 'quantity' => 1],
        ]);
        $page = Livewire::test(ProductVariantsRelationManager::class, ['ownerRecord' => $root, 'pageClass' => EditProduct::class]);
        $page->mountTableAction('editKitComposition', $offer)
            ->assertActionDataSet(['sku_'.$group->id => $sku->id, 'qty_'.$group->id => 1, 'bundle_price' => null])
            ->assertFormFieldExists('sku_'.$group->id, fn (Select $field): bool => $field->isPreloaded() && isset($field->getOptions()[$sku->id], $field->getOptions()[$other->id]));
        $page->setActionData(['sku_'.$group->id => $sku->id, 'qty_'.$group->id => 1, 'bundle_price' => 1300])
            ->callMountedAction()->assertHasNoActionErrors();
        $this->assertEquals(1300, $offer->fresh()->bundle_price);
        $this->assertEquals(1300, $offer->fresh()->price);
        $page->mountTableAction('editKitComposition', $offer)
            ->setActionData(['sku_'.$group->id => $sku->id, 'qty_'.$group->id => 1, 'bundle_price' => ''])
            ->callMountedAction()->assertHasNoActionErrors();
        $this->assertNull($offer->fresh()->bundle_price);
        $this->assertEquals(1500, $offer->fresh()->price);
    }
}
