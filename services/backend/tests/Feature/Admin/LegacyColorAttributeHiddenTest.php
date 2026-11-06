<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Products\Pages\EditProduct;
use App\Models\Product\Attribute;
use App\Models\Product\Category;
use App\Models\Product\Product;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LegacyColorAttributeHiddenTest extends TestCase
{
    public function test_legacy_color_is_hidden_but_kept_in_database(): void
    {
        Role::findOrCreate('super_admin', 'web');
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin_sv'));

        $colorId = DB::table('product_attributes')->insertGetId(['name' => 'Цвет', 'slug' => 'color', 'type' => 'color', 'is_filterable' => true, 'is_use_in_variations' => true]);
        $valueId = DB::table('product_attribute_values')->insertGetId(['attribute_id' => $colorId, 'value' => 'Белый', 'slug' => 'belyi']);
        $material = Attribute::create(['name' => 'Материал', 'slug' => 'material-test', 'type' => 'string', 'is_filterable' => true, 'allow_custom_value' => true]);
        $category = Category::factory()->create();
        $product = Product::factory()->create(['slug' => 'legacy-color-hidden', 'is_variable' => false, 'state' => 'active']);
        $product->taxons()->attach($category);
        DB::table('product_product_attributes')->insert([
            ['product_id' => $product->id, 'attribute_id' => $colorId, 'attribute_value_id' => $valueId, 'custom_value' => null],
            ['product_id' => $product->id, 'attribute_id' => $material->id, 'attribute_value_id' => null, 'custom_value' => 'Дерево'],
        ]);

        $this->assertNull(Attribute::find($colorId));
        $this->assertSame([$colorId], Attribute::hiddenIds());
        $this->assertSame([$material->id], $product->attributes()->pluck('product_attributes.id')->all());

        Livewire::test(EditProduct::class, ['record' => $product->id])->call('save')->assertHasNoFormErrors();
        $this->assertTrue(DB::table('product_product_attributes')->where('product_id', $product->id)->where('attribute_id', $colorId)->exists());

        $card = $this->getJson('/api/v1/products/legacy-color-hidden')->assertOk()->json('product');
        $this->assertNotContains('color', array_column($card['specifications'] ?? [], 'slug'));
        $filters = $this->getJson('/api/v1/products?category_slug='.$category->slug)->assertOk()->json('meta.filters.attributes');
        $this->assertNotContains('color', array_column($filters, 'slug'));
        $this->assertContains('material-test', array_column($filters, 'slug'));
    }
}
