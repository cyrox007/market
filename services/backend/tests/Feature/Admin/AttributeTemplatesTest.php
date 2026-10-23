<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Products\AttributeTemplates\Pages\CreateAttributeTemplate;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Models\Product\Attribute;
use App\Models\Product\AttributeTemplate;
use App\Models\Product\Product;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AttributeTemplatesTest extends TestCase
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

    public function test_operator_creates_template_with_ordered_attributes(): void
    {
        $width = $this->attribute('Ширина (мм)');
        $material = $this->attribute('Материал');
        $undoRepeaterFake = Repeater::fake();

        Livewire::test(CreateAttributeTemplate::class)
            ->fillForm([
                'name' => 'Диваны',
                'items' => [
                    ['attribute_id' => $material->id],
                    ['attribute_id' => $width->id],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $undoRepeaterFake();

        $template = AttributeTemplate::query()->sole();
        $this->assertSame('Диваны', $template->name);
        $this->assertSame([$material->id, $width->id], $template->items->pluck('attribute_id')->all());
    }

    public function test_template_rejects_same_attribute_twice(): void
    {
        $material = $this->attribute('Материал');
        $undoRepeaterFake = Repeater::fake();

        Livewire::test(CreateAttributeTemplate::class)
            ->fillForm([
                'name' => 'Диваны',
                'items' => [
                    ['attribute_id' => $material->id],
                    ['attribute_id' => $material->id],
                ],
            ])
            ->call('create')
            ->assertHasFormErrors();

        $undoRepeaterFake();

        $this->assertSame(0, AttributeTemplate::count());
    }

    public function test_applying_template_adds_only_missing_attributes(): void
    {
        $width = $this->attribute('Ширина (мм)');
        $material = $this->attribute('Материал');
        $color = $this->attribute('Цвет', ['slug' => Attribute::SLUG_COLOR, 'is_use_in_variations' => true]);
        $template = $this->template('Диваны', [$width, $material, $color]);

        $product = Product::factory()->create(['state' => 'active']);
        DB::table('product_product_attributes')->insert([
            'product_id' => $product->id,
            'attribute_id' => $material->id,
            'custom_value' => 'Велюр',
        ]);

        $page = Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->callAction(
                TestAction::make('applyAttributeTemplate')->schemaComponent('product_attributes_section', 'form'),
                ['template_id' => $template->id],
            )
            ->assertHasNoFormErrors()
            // Второе применение того же шаблона.
            ->callAction(
                TestAction::make('applyAttributeTemplate')->schemaComponent('product_attributes_section', 'form'),
                ['template_id' => $template->id],
            );

        $rows = array_values($page->get('data.product_attributes'));
        $this->assertSame([$material->id, $width->id, $color->id], array_map('intval', array_column($rows, 'attribute_id')));
        $this->assertSame('Велюр', $rows[0]['custom_value']);
        $this->assertNull($rows[1]['attribute_value_id']);
        $this->assertSame(2, $template->fresh()->applied_count);
    }

    public function test_variable_product_accepts_shared_variation_attribute_defaults(): void
    {
        $material = $this->attribute('Материал');
        $color = $this->attribute('Цвет', ['slug' => Attribute::SLUG_COLOR, 'is_use_in_variations' => true]);
        $template = $this->template('Диваны', [$color, $material]);

        $product = Product::factory()->create(['state' => 'active', 'is_variable' => true]);

        $rows = Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->callAction(
                TestAction::make('applyAttributeTemplate')->schemaComponent('product_attributes_section', 'form'),
                ['template_id' => $template->id],
            )
            ->get('data.product_attributes');

        $this->assertSame([$color->id, $material->id], array_map('intval', array_column(array_values($rows), 'attribute_id')));
    }

    public function test_product_attribute_table_has_scoped_dropdown_overflow_override(): void
    {
        $product = Product::factory()->create();
        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->assertSee('product-attributes-table');

        $css = file_get_contents(resource_path('css/filament/admin_sv/theme.css'));
        $this->assertMatchesRegularExpression('/\.fi-fo-table-repeater\.product-attributes-table\s*>\s*table\s*\{\s*overflow:\s*visible;\s*\}/', $css);
    }

    public function test_offer_edit_opens_with_single_and_multiple_selected_values(): void
    {
        $single = $this->attribute('Материал', ['type' => 'select', 'is_use_in_variations' => true, 'is_multiple' => false]);
        $multiple = $this->attribute('Оттенки', ['type' => 'select', 'is_use_in_variations' => true, 'is_multiple' => true]);
        $parent = Product::factory()->create(['is_variable' => true]);
        $variant = Product::factory()->create(['parent_product_id' => $parent->id]);
        $parent->variationAttributeSelection()->sync([$single->id, $multiple->id]);
        foreach ([$single, $multiple] as $attribute) {
            $value = $attribute->values()->create(['value' => 'Тест', 'slug' => 'test']);
            DB::table('product_variant_attributes')->insert([
                'product_id' => $variant->id, 'attribute_id' => $attribute->id,
                'attribute_value_id' => $value->id,
            ]);
        }
        Livewire::test(\App\Filament\Resources\Products\RelationManagers\ProductVariantsRelationManager::class, [
            'ownerRecord' => $parent, 'pageClass' => EditProduct::class,
        ])->mountTableAction('edit', $variant)->assertStatus(200);
    }

    public function test_multiple_product_values_load_in_one_row_and_survive_save(): void
    {
        $attribute = $this->attribute('Материалы', ['type' => 'select', 'is_multiple' => true]);
        $first = $attribute->values()->create(['value' => 'Дерево', 'slug' => 'wood']);
        $second = $attribute->values()->create(['value' => 'Металл', 'slug' => 'metal']);
        $product = Product::factory()->create();
        app(\App\Services\Product\ProductAttributeSyncService::class)->sync($product, [
            ['attribute_id' => $attribute->id, 'attribute_value_id' => [$first->id, $second->id]],
        ]);
        $page = Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()]);
        $rows = array_values($page->get('data.product_attributes'));
        $this->assertCount(1, $rows);
        $this->assertEqualsCanonicalizing([$first->id, $second->id], $rows[0]['attribute_value_id']);
        $page->call('save', false)->assertHasNoFormErrors();
        $this->assertEqualsCanonicalizing([$first->id, $second->id], DB::table('product_product_attributes')
            ->where('product_id', $product->id)->where('attribute_id', $attribute->id)->pluck('attribute_value_id')->all());
    }

    private function attribute(string $name, array $overrides = []): Attribute
    {
        return Attribute::query()->create(array_merge([
            'name' => $name,
            'slug' => Str::slug($name),
            'type' => 'string',
            'allow_custom_value' => true,
        ], $overrides));
    }

    /**
     * @param  list<Attribute>  $attributes
     */
    private function template(string $name, array $attributes): AttributeTemplate
    {
        $template = AttributeTemplate::query()->create(['name' => $name]);
        foreach ($attributes as $index => $attribute) {
            $template->items()->create(['attribute_id' => $attribute->id, 'sort_order' => $index + 1]);
        }

        return $template;
    }
}
