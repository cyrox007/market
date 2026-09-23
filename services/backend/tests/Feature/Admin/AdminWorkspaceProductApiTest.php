<?php

namespace Tests\Feature\Admin;

use App\Models\Product\Category;
use App\Models\Product\Product;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminWorkspaceProductApiTest extends TestCase
{
    public function test_existing_product_editor_endpoint_returns_real_product_data(): void
    {
        $this->withoutExceptionHandling();

        $permission = Permission::firstOrCreate([
            'name' => 'view products',
            'guard_name' => 'web',
        ]);

        $role = Role::firstOrCreate([
            'name' => 'super_admin',
            'guard_name' => 'web',
        ]);
        $role->givePermissionTo($permission);

        $user = User::factory()->create();
        $user->assignRole($role);

        $category = Category::factory()->create([
            'name' => 'Диваны',
            'slug' => 'divany',
        ]);

        $product = Product::factory()->create([
            'name' => 'Диван для проверки редактора',
            'slug' => 'divan-editor-contract',
            'sku' => 'SOFA-EDITOR-001',
            'gtin' => '4600000000001',
            'state' => 'active',
            'price' => 45990,
            'original_price' => 49990,
            'stock' => 7,
            'priority' => 12,
            'description' => '<p>Описание существующего товара</p>',
            'parent_product_id' => null,
        ]);

        $product->taxons()->attach($category->id);

        $response = $this->actingAs($user, 'web')
            ->getJson("/admin_sv/api/products/{$product->id}");

        $response->assertOk()
            ->assertJsonPath('product.id', $product->id)
            ->assertJsonPath('product.name', 'Диван для проверки редактора')
            ->assertJsonPath('product.slug', 'divan-editor-contract')
            ->assertJsonPath('product.sku', 'SOFA-EDITOR-001')
            ->assertJsonPath('product.gtin', '4600000000001')
            ->assertJsonPath('product.state', 'active')
            ->assertJsonPath('product.price', 45990)
            ->assertJsonPath('product.original_price', 49990)
            ->assertJsonPath('product.stock', 7)
            ->assertJsonPath('product.priority', 12)
            ->assertJsonPath('product.description', '<p>Описание существующего товара</p>')
            ->assertJsonPath('product.category_ids.0', $category->id)
            ->assertJsonPath('product.categories.0.id', $category->id)
            ->assertJsonPath('product.categories.0.name', 'Диваны')
            ->assertJsonStructure([
                'product' => [
                    'id',
                    'name',
                    'slug',
                    'sku',
                    'gtin',
                    'state',
                    'price',
                    'original_price',
                    'stock',
                    'priority',
                    'description',
                    'category_ids',
                    'categories',
                    'attribute_rows',
                    'attributes',
                    'variation_attribute_ids',
                    'variants',
                    'warehouse_stocks',
                    'media',
                ],
            ]);
    }
}
