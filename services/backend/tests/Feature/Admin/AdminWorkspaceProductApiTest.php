<?php

namespace Tests\Feature\Admin;

use App\Models\Product\Attribute;
use App\Models\Product\AttributeValue;
use App\Models\Inventory\Warehouse;
use App\Models\Product\Category;
use App\Models\Product\Product;
use App\Models\Settings\ProductStockSettings;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminWorkspaceProductApiTest extends TestCase
{
    public function test_full_variant_lifecycle_with_color_and_warehouse_stock(): void
    {
        $permissions = collect([
            'view products',
            'create products',
            'update products',
            'delete products',
        ])->map(fn (string $name) => Permission::firstOrCreate([
            'name' => $name,
            'guard_name' => 'web',
        ]));

        $role = Role::firstOrCreate([
            'name' => 'variant_lifecycle_test',
            'guard_name' => 'web',
        ]);
        $role->syncPermissions($permissions);

        $user = User::factory()->create();
        $user->assignRole($role);

        $settings = ProductStockSettings::getInstance();
        $settings->warehouse_accounting_enabled = true;
        $settings->fallback_to_first_warehouse = true;
        $settings->save();

        $warehouse = Warehouse::query()->create([
            'external_id' => 'warehouse-variant-test',
            'name' => 'Тестовый склад вариаций',
            'is_active' => true,
        ]);

        $parent = Product::factory()->create([
            'name' => 'Диван для полного сценария вариаций',
            'slug' => 'variant-lifecycle-parent',
            'sku' => 'PARENT-LIFECYCLE',
            'state' => 'active',
            'price' => 9000,
            'parent_product_id' => null,
            'is_variable' => false,
        ]);

        $color = Attribute::query()->create([
            'name' => 'Цвет',
            'slug' => Attribute::SLUG_COLOR,
            'type' => 'color',
            'is_filterable' => true,
            'is_required' => false,
            'is_use_in_variations' => true,
            'allow_custom_value' => false,
            'is_multiple' => false,
            'sort_order' => 10,
        ]);

        $gray = AttributeValue::query()->create([
            'attribute_id' => $color->id,
            'value' => 'Серый',
            'slug' => 'seryi',
            'color_code' => '#808080',
            'sort_order' => 10,
        ]);

        $selectionResponse = $this->actingAs($user, 'web')
            ->putJson("/admin_sv/api/products/{$parent->id}/variation-attributes", [
                'attribute_ids' => [$color->id],
            ]);

        $selectionResponse->assertOk();

        $createResponse = $this->actingAs($user, 'web')
            ->postJson("/admin_sv/api/products/{$parent->id}/variants", [
                'name' => 'Диван — Серый',
                'sku' => 'VAR-LIFECYCLE-001',
                'price' => 9801,
                'original_price' => null,
                'stock' => 0,
                'backorder' => false,
                'state' => 'active',
                'external_id' => null,
                'warehouse_stocks' => [
                    [
                        'warehouse_id' => $warehouse->id,
                        'quantity' => 15,
                    ],
                ],
                'attributes' => [
                    [
                        'attribute_id' => $color->id,
                        'attribute_value_id' => [$gray->id],
                        'custom_value' => '',
                    ],
                ],
            ]);

        $createResponse->assertOk()
            ->assertJsonPath('message', 'Торговое предложение создано');

        $variant = Product::query()
            ->where('parent_product_id', $parent->id)
            ->where('sku', 'VAR-LIFECYCLE-001')
            ->firstOrFail();

        $this->assertDatabaseHas('product_variant_attributes', [
            'product_id' => $variant->id,
            'attribute_id' => $color->id,
            'attribute_value_id' => $gray->id,
        ]);
        $this->assertDatabaseHas('product_warehouse_stocks', [
            'product_id' => $variant->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 15,
        ]);

        $editorResponse = $this->actingAs($user, 'web')
            ->getJson("/admin_sv/api/products/{$parent->id}/editor");

        $editorResponse->assertOk()
            ->assertJsonPath('product.id', $parent->id)
            ->assertJsonCount(1, 'product.variants')
            ->assertJsonPath('product.variants.0.id', $variant->id)
            ->assertJsonPath('product.variants.0.sku', 'VAR-LIFECYCLE-001')
            ->assertJsonPath('product.variants.0.attributes.0.attribute_id', $color->id)
            ->assertJsonPath('product.variants.0.attributes.0.attribute_value_id.0', $gray->id)
            ->assertJsonPath('product.variants.0.warehouse_stocks.0.warehouse_id', $warehouse->id)
            ->assertJsonPath('product.variants.0.warehouse_stocks.0.quantity', 15);

        $updateResponse = $this->actingAs($user, 'web')
            ->putJson("/admin_sv/api/products/{$parent->id}/variants/{$variant->id}", [
                'name' => 'Диван — Серый обновлённый',
                'sku' => 'VAR-LIFECYCLE-001',
                'price' => 9999,
                'original_price' => 10999,
                'stock' => 0,
                'backorder' => true,
                'state' => 'active',
                'external_id' => null,
                'warehouse_stocks' => [
                    [
                        'warehouse_id' => $warehouse->id,
                        'quantity' => 12,
                    ],
                ],
                'attributes' => [
                    [
                        'attribute_id' => $color->id,
                        'attribute_value_id' => [$gray->id],
                        'custom_value' => '',
                    ],
                ],
            ]);

        $updateResponse->assertOk()
            ->assertJsonPath('message', 'Торговое предложение сохранено');

        $variant->refresh();
        $this->assertSame('Диван — Серый обновлённый', $variant->name);
        $this->assertSame(9999.0, (float) $variant->price);
        $this->assertTrue((bool) $variant->backorder);

        $this->assertDatabaseHas('product_warehouse_stocks', [
            'product_id' => $variant->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 12,
        ]);

        $deleteResponse = $this->actingAs($user, 'web')
            ->deleteJson("/admin_sv/api/products/{$parent->id}/variants/{$variant->id}");

        $deleteResponse->assertOk()
            ->assertJsonPath('message', 'Торговое предложение удалено');

        $this->assertDatabaseMissing('products', [
            'id' => $variant->id,
        ]);

        $parent->refresh();
        $this->assertFalse($parent->isVariable());
    }

    public function test_variant_creation_persists_required_product_fields(): void
    {
        $createPermission = Permission::firstOrCreate([
            'name' => 'create products',
            'guard_name' => 'web',
        ]);
        $updatePermission = Permission::firstOrCreate([
            'name' => 'update products',
            'guard_name' => 'web',
        ]);

        $role = Role::firstOrCreate([
            'name' => 'variant_creation_test',
            'guard_name' => 'web',
        ]);
        $role->syncPermissions([$createPermission, $updatePermission]);

        $user = User::factory()->create();
        $user->assignRole($role);

        $parent = Product::factory()->create([
            'name' => 'Родительский товар',
            'slug' => 'parent-product',
            'sku' => 'PARENT-001',
            'state' => 'active',
            'price' => 9000,
            'parent_product_id' => null,
            'is_variable' => false,
        ]);

        $variantAttribute = Attribute::ensureVariantAttribute();

        DB::table('product_variation_attribute_selection')->insert([
            'product_id' => $parent->id,
            'attribute_id' => $variantAttribute->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($user, 'web')
            ->postJson("/admin_sv/api/products/{$parent->id}/variants", [
                'name' => 'Родительский товар — Серый',
                'sku' => 'VARIANT-001',
                'price' => 9801,
                'original_price' => null,
                'stock' => 15,
                'backorder' => false,
                'state' => 'active',
                'external_id' => null,
                'warehouse_stocks' => [],
                'attributes' => [],
            ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Торговое предложение создано')
            ->assertJsonPath('product.id', $parent->id);

        $variant = Product::query()
            ->where('parent_product_id', $parent->id)
            ->where('sku', 'VARIANT-001')
            ->first();

        $this->assertNotNull($variant);
        $this->assertSame('Родительский товар — Серый', $variant->name);
        $this->assertSame('VARIANT-001', $variant->sku);
        $this->assertSame(9801.0, (float) $variant->price);
        $this->assertNotSame('', (string) $variant->slug);

        $this->assertDatabaseHas('product_variant_attributes', [
            'product_id' => $variant->id,
            'attribute_id' => $variantAttribute->id,
            'custom_value' => 'Родительский товар — Серый',
        ]);
    }

    public function test_variation_attributes_are_saved_with_product_id(): void
    {
        $updatePermission = Permission::firstOrCreate([
            'name' => 'update products',
            'guard_name' => 'web',
        ]);

        $role = Role::firstOrCreate([
            'name' => 'variation_editor_test',
            'guard_name' => 'web',
        ]);
        $role->givePermissionTo($updatePermission);

        $user = User::factory()->create();
        $user->assignRole($role);

        $product = Product::factory()->create([
            'name' => 'Товар для вариаций',
            'slug' => 'variation-product',
            'sku' => 'VAR-001',
            'state' => 'active',
            'price' => 1000,
            'parent_product_id' => null,
        ]);

        $color = Attribute::query()->create([
            'name' => 'Цвет',
            'slug' => Attribute::SLUG_COLOR,
            'type' => 'color',
            'is_filterable' => true,
            'is_required' => false,
            'is_use_in_variations' => true,
            'allow_custom_value' => false,
            'is_multiple' => false,
            'sort_order' => 10,
        ]);

        $response = $this->actingAs($user, 'web')
            ->putJson("/admin_sv/api/products/{$product->id}/variation-attributes", [
                'attribute_ids' => [$color->id],
            ]);

        $response->assertOk()
            ->assertJsonPath('product.id', $product->id)
            ->assertJsonPath('message', 'Параметры вариаций сохранены');

        $this->assertDatabaseHas('product_variation_attribute_selection', [
            'product_id' => $product->id,
            'attribute_id' => $color->id,
        ]);

        $this->assertDatabaseMissing('product_variation_attribute_selection', [
            'product_id' => null,
            'attribute_id' => $color->id,
        ]);
    }

    public function test_product_validation_errors_are_returned_in_russian(): void
    {
        $viewPermission = Permission::firstOrCreate([
            'name' => 'view products',
            'guard_name' => 'web',
        ]);
        $updatePermission = Permission::firstOrCreate([
            'name' => 'update products',
            'guard_name' => 'web',
        ]);

        $role = Role::firstOrCreate([
            'name' => 'product_editor_test',
            'guard_name' => 'web',
        ]);
        $role->syncPermissions([$viewPermission, $updatePermission]);

        $user = User::factory()->create();
        $user->assignRole($role);

        $existing = Product::factory()->create([
            'name' => 'Товар с занятым адресом',
            'slug' => 'zanyatyi-slug',
            'sku' => 'TAKEN-001',
            'state' => 'active',
            'price' => 1000,
            'parent_product_id' => null,
        ]);

        $product = Product::factory()->create([
            'name' => 'Редактируемый товар',
            'slug' => 'editable-product',
            'sku' => 'EDIT-001',
            'state' => 'active',
            'price' => 2000,
            'parent_product_id' => null,
        ]);

        $response = $this->actingAs($user, 'web')
            ->putJson("/admin_sv/api/products/{$product->id}", [
                'name' => 'Редактируемый товар',
                'slug' => $existing->slug,
                'sku' => 'EDIT-001',
                'gtin' => null,
                'description' => null,
                'state' => 'active',
                'priority' => 0,
                'price' => 2000,
                'original_price' => null,
                'category_ids' => [],
                'stock' => 0,
                'backorder' => false,
                'length' => null,
                'width' => null,
                'height' => null,
                'weight' => null,
                'tax_category_id' => null,
                'shipping_category_id' => null,
                'manufacturer_id' => null,
                'warehouse_stocks' => [],
            ]);

        $response->assertStatus(422)
            ->assertJsonPath(
                'errors.slug.0',
                'Такое значение поля «URL (slug)» уже используется.'
            );
    }

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
            ->getJson("/admin_sv/api/products/{$product->id}/editor");

        $response->assertOk()
            ->assertHeader('Cache-Control', 'no-store, no-cache, must-revalidate')
            ->assertJsonPath('contract_version', 2)
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
                'options' => [
                    'attributes',
                    'variation_attributes',
                    'available_variation_attributes',
                    'shipping_locations',
                    'manufacturers',
                    'tax_categories',
                    'shipping_categories',
                    'warehouses',
                    'stock_settings' => [
                        'warehouse_accounting_enabled',
                        'fallback_to_first_warehouse',
                    ],
                ],
            ]);
    }
}
