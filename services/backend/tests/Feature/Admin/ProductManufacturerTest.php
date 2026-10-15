<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Models\Product\Attribute;
use App\Models\Product\AttributeValue;
use App\Models\Product\Manufacturer;
use App\Models\Product\Product;
use App\Models\User;
use App\Services\Product\ProductManufacturer;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProductManufacturerTest extends TestCase
{
    public function test_free_text_is_remembered_reused_and_clearable_without_entity(): void
    {
        $service = app(ProductManufacturer::class);
        $a = Product::factory()->create();
        $b = Product::factory()->create();
        $service->save($a, '  Мебель   Плюс  ');
        $service->save($b, 'мебель плюс');
        $this->assertSame('Мебель Плюс', $service->value($a));
        $this->assertSame('Мебель Плюс', $service->value($b));
        $this->assertCount(1, $service->options());
        $this->assertSame(0, Manufacturer::count());
        $service->save($a, '');
        $this->assertNull($service->value($a));
        $this->assertCount(1, $service->options());
    }

    public function test_catalog_returns_manufacturer_and_shared_facets_and_count(): void
    {
        $a = Product::factory()->create();
        $b = Product::factory()->create();
        app(ProductManufacturer::class)->save($a, 'Фабрика Альфа');
        app(ProductManufacturer::class)->save($b, 'Фабрика Бета');
        $category = \App\Models\Product\Category::factory()->create();
        $a->taxons()->attach($category);
        $b->taxons()->attach($category);
        $slug = app(ProductManufacturer::class)->options()[0]['slug'];
        $this->getJson('/api/v1/manufacturers')->assertOk()->assertJsonCount(2, 'data');
        $response = $this->getJson('/api/v1/products?category_slug='.$category->slug.'&attr_proizvoditel='.$slug)->assertOk();
        $response->assertJsonCount(1, 'data')->assertJsonPath('data.0.manufacturer', 'Фабрика Альфа');
        $this->getJson('/api/v1/products/count?manufacturers='.$slug)->assertOk()->assertJsonPath('total', 1);
        $this->assertCount(2, $response->json('meta.filters.manufacturers'));
    }

    public function test_form_creates_edits_and_clears_manufacturer(): void
    {
        Role::findOrCreate('super_admin', 'web');
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin_sv'));
        Livewire::test(CreateProduct::class)->fillForm([
            'name' => 'Тестовый стол', 'slug' => 'manufacturer-table', 'sku' => 'manufacturer-table',
            'price' => 1000, 'stock' => 10, 'state' => 'active', 'manufacturer_name' => 'Своя фабрика',
        ])->call('create')->assertHasNoFormErrors();
        $product = Product::where('slug', 'manufacturer-table')->firstOrFail();
        $this->assertSame('Своя фабрика', app(ProductManufacturer::class)->value($product));
        Livewire::test(EditProduct::class, ['record' => $product->id])->assertFormSet(['manufacturer_name' => 'Своя фабрика'])
            ->fillForm(['manufacturer_name' => 'Другая фабрика'])->call('save')->assertHasNoFormErrors();
        $this->assertSame('Другая фабрика', app(ProductManufacturer::class)->value($product->fresh()));
        Livewire::test(EditProduct::class, ['record' => $product->id])->fillForm(['manufacturer_name' => null])->call('save')->assertHasNoFormErrors();
        $this->assertNull(app(ProductManufacturer::class)->value($product->fresh()));
    }

    public function test_admin_filter_matches_saved_values(): void
    {
        $a = Product::factory()->create();
        Product::factory()->create();
        app(ProductManufacturer::class)->save($a, 'Фабрика');
        $this->assertSame([$a->id], app(ProductManufacturer::class)->filter(Product::query(), 'Фабрика')->pluck('id')->all());
    }

    public function test_variant_inherits_parent_and_can_override_it(): void
    {
        $service = app(ProductManufacturer::class);
        $root = Product::factory()->create(['is_variable' => true]);
        $variant = Product::factory()->create(['parent_product_id' => $root->id]);
        $service->save($root, 'Основная фабрика');
        $this->assertSame('Основная фабрика', $service->value($variant));
        $service->save($variant, 'Другая фабрика');
        $this->assertSame('Другая фабрика', $service->value($variant));
        $this->assertContains($root->id, $service->filter(Product::query()->whereNull('parent_product_id'), 'Другая фабрика')->pluck('id')->all());
    }

    public function test_existing_custom_text_is_available_and_slug_collisions_do_not_merge_names(): void
    {
        $service = app(ProductManufacturer::class);
        $product = Product::factory()->create();
        $attribute = $service->attribute();
        $product->attributes()->attach($attribute->id, ['custom_value' => 'Старая фабрика']);
        $this->assertSame('Старая фабрика', $service->value($product));
        $this->assertSame('Старая фабрика', $service->options()[0]['name']);
        $service->save($product, 'ABC');
        $service->save(Product::factory()->create(), 'A B C');
        $this->assertCount(2, AttributeValue::where('attribute_id', $attribute->id)->get());
    }
}
