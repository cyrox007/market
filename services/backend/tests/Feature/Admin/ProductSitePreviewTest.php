<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\RelationManagers\ProductVariantsRelationManager;
use App\Models\Product\Product;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProductSitePreviewTest extends TestCase
{
    public function test_preview_belongs_to_simple_products_and_individual_offers(): void
    {
        Role::findOrCreate('super_admin', 'web');
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin_sv'));
        $simple = Product::factory()->create();
        $group = Product::factory()->create(['is_variable' => true]);
        $offer = Product::factory()->create(['parent_product_id' => $group->id]);
        Livewire::test(EditProduct::class, ['record' => $simple->getRouteKey()])
            ->assertActionVisible('viewOnSite')
            ->assertActionHasUrl('viewOnSite', url('/product/'.$simple->slug));
        Livewire::test(EditProduct::class, ['record' => $group->getRouteKey()])
            ->assertActionHidden('viewOnSite');
        Livewire::test(ProductVariantsRelationManager::class, ['ownerRecord' => $group, 'pageClass' => EditProduct::class])
            ->assertTableActionVisible('viewOnSite', $offer)
            ->assertTableActionHasUrl('viewOnSite', url('/product/'.$offer->slug), $offer);
    }
}
