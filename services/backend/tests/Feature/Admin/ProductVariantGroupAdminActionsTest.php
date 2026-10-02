<?php

namespace Tests\Feature\Admin;

use App\Actions\Product\Data\MergeProductsIntoVariableProductData;
use App\Actions\Product\MergeProductsIntoVariableProductAction;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Products\RelationManagers\ProductVariantsRelationManager;
use App\Models\Product\Attribute;
use App\Models\Product\Product;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Связка «форма админки → действие» для объединения, отвязки и привязки вариантов.
 */
class ProductVariantGroupAdminActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Attribute::ensureVariantAttribute();

        Role::findOrCreate('super_admin', 'web');
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        Filament::setCurrentPanel(Filament::getPanel('admin_sv'));
    }

    public function test_merge_bulk_action_passes_form_name_and_labels_to_merge(): void
    {
        $white = Product::factory()->create(['name' => '085.001.206 Стенка МАРТА-11 Белый гладкий']);
        $oak = Product::factory()->create(['name' => '085.001.205 Стенка МАРТА-11 Венге/Лоредо']);

        Livewire::test(ListProducts::class)
            ->callTableBulkAction('merge_into_variable', [$white, $oak], data: [
                'parent_name' => 'Стенка «Марта»',
                'variants' => [
                    ['product_id' => $white->id, 'variant_label' => 'Белый'],
                    ['product_id' => $oak->id, 'variant_label' => 'Венге'],
                ],
            ])
            ->assertHasNoTableBulkActionErrors();

        $parent = Product::query()->where('name', 'Стенка «Марта»')->first();
        $this->assertNotNull($parent);
        $this->assertSame($parent->id, $white->fresh()->parent_product_id);
        $this->assertSame($parent->id, $oak->fresh()->parent_product_id);
        $this->assertDatabaseHas('product_variant_attributes', ['product_id' => $white->id, 'custom_value' => 'Белый']);
        $this->assertDatabaseHas('product_variant_attributes', ['product_id' => $oak->id, 'custom_value' => 'Венге']);
    }

    public function test_merge_bulk_action_prefills_auto_name_and_labels(): void
    {
        $white = Product::factory()->create(['name' => '085.001.206 Стенка МАРТА-11 Белый гладкий']);
        $oak = Product::factory()->create(['name' => '085.001.205 Стенка МАРТА-11 Венге/Лоредо']);

        Livewire::test(ListProducts::class)
            ->callTableBulkAction('merge_into_variable', [$white, $oak])
            ->assertHasNoTableBulkActionErrors();

        $parent = Product::query()->where('name', 'Стенка МАРТА-11')->first();
        $this->assertNotNull($parent);
        $this->assertDatabaseHas('product_variant_attributes', ['product_id' => $white->id, 'custom_value' => 'Белый гладкий']);
        $this->assertDatabaseHas('product_variant_attributes', ['product_id' => $oak->id, 'custom_value' => 'Венге/Лоредо']);
    }

    public function test_detach_action_unlinks_variant_and_reloads_page(): void
    {
        [$parent, $a] = $this->mergedGroup();

        Livewire::test(ProductVariantsRelationManager::class, ['ownerRecord' => $parent, 'pageClass' => EditProduct::class])
            ->assertTableActionEnabled('detach', $a)
            ->callTableAction('detach', $a)
            ->assertHasNoTableActionErrors()
            ->assertRedirect();

        $this->assertNull($a->fresh()->parent_product_id);
        $this->assertSame(1, $parent->variants()->count());
    }

    public function test_detach_action_is_disabled_for_group_loaded_from_one_c(): void
    {
        [$parent, $a] = $this->mergedGroup();
        $parent->update(['external_id' => 'ext-parent']);

        Livewire::test(ProductVariantsRelationManager::class, ['ownerRecord' => $parent->fresh(), 'pageClass' => EditProduct::class])
            ->assertTableActionDisabled('detach', $a);

        $this->assertSame($parent->id, $a->fresh()->parent_product_id);
    }

    public function test_attach_action_links_selected_product_with_label(): void
    {
        [$parent] = $this->mergedGroup();
        $forgotten = Product::factory()->create(['name' => 'Стол серый']);

        $component = Livewire::test(ProductVariantsRelationManager::class, ['ownerRecord' => $parent, 'pageClass' => EditProduct::class])
            ->mountTableAction('attachExisting');

        // Форма открывается с одной пустой строкой — заполняем её, как это делает редактор
        $rowKey = array_key_first($component->get('mountedActions.0.data.items'));

        $component
            ->set("mountedActions.0.data.items.{$rowKey}.product_id", $forgotten->id)
            ->set("mountedActions.0.data.items.{$rowKey}.variant_label", 'Серый матовый')
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors()
            ->assertRedirect();

        $this->assertSame($parent->id, $forgotten->fresh()->parent_product_id);
        $this->assertDatabaseHas('product_variant_attributes', ['product_id' => $forgotten->id, 'custom_value' => 'Серый матовый']);
    }

    public function test_attach_action_is_hidden_for_simple_product(): void
    {
        $simple = Product::factory()->create(['is_variable' => false]);

        Livewire::test(ProductVariantsRelationManager::class, ['ownerRecord' => $simple, 'pageClass' => EditProduct::class])
            ->assertTableActionHidden('attachExisting');
    }

    /**
     * @return array{0: Product, 1: Product, 2: Product}
     */
    protected function mergedGroup(): array
    {
        $a = Product::factory()->create(['name' => 'Стол белый']);
        $b = Product::factory()->create(['name' => 'Стол чёрный']);

        $parent = app(MergeProductsIntoVariableProductAction::class)->execute(
            new MergeProductsIntoVariableProductData(productIds: [$a->id, $b->id]),
        );

        return [$parent, $a->fresh(), $b->fresh()];
    }
}
