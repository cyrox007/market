<?php

namespace Tests\Feature\Api;

use App\Filament\Resources\Products\Pages\ListProducts;
use App\Models\Product\Product;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ExternalIdSearchTest extends TestCase
{
    public function test_public_search_finds_own_external_code_without_matching_sibling(): void
    {
        $parent = Product::factory()->create(['is_variable' => true, 'state' => 'active']);
        $offer = Product::factory()->create(['parent_product_id' => $parent->id, 'state' => 'active', 'sku' => 'SHOP-A', 'external_id' => 'ONEC-729001']);
        Product::factory()->create(['parent_product_id' => $parent->id, 'state' => 'active', 'sku' => 'SHOP-B', 'external_id' => 'ONEC-729002']);
        $simple = Product::factory()->create(['state' => 'active', 'sku' => 'SHOP-C', 'external_id' => 'ONEC-729003']);
        foreach (['ONEC-729001' => $offer, 'ONEC-729003' => $simple, 'SHOP-A' => $offer] as $term => $expected) {
            $this->getJson('/api/v1/products/search?q='.$term)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $expected->id);
        }
    }

    public function test_public_search_matches_all_words_in_any_order(): void
    {
        $product = Product::factory()->create(['name' => 'комплект из 2-х ящиков', 'state' => 'active']);
        Product::factory()->create(['name' => 'комплект полок', 'state' => 'active']);
        Product::factory()->create(['name' => 'Набор ящиков', 'state' => 'active']);

        foreach (['комплект ящиков', 'комплект из 2-х ящиков', 'из 2-х ящиков комплект'] as $term) {
            $this->getJson('/api/v1/products/search?'.http_build_query(['q' => $term]))
                ->assertOk()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.id', $product->id);
        }
    }

    public function test_admin_catalog_finds_parent_by_offer_code_and_simple_product_by_own_code(): void
    {
        Role::findOrCreate('super_admin', 'web');
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin_sv'));
        $parent = Product::factory()->create(['is_variable' => true, 'state' => 'active']);
        Product::factory()->create(['parent_product_id' => $parent->id, 'state' => 'active', 'sku' => 'SHOP-A', 'external_id' => 'ONEC-729001']);
        $simple = Product::factory()->create(['state' => 'active', 'sku' => 'SHOP-C', 'external_id' => 'ONEC-729003']);
        Livewire::test(ListProducts::class)->searchTable('ONEC-729001')->assertCanSeeTableRecords([$parent])->assertCanNotSeeTableRecords([$simple]);
        Livewire::test(ListProducts::class)->searchTable('ONEC-729003')->assertCanSeeTableRecords([$simple])->assertCanNotSeeTableRecords([$parent]);
        Livewire::test(ListProducts::class)->searchTable('SHOP-A')->assertCanSeeTableRecords([$parent]);
    }
}