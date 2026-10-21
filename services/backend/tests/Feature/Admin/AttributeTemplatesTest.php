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

    public function test_variable_product_skips_variation_attributes(): void
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

        $this->assertSame([$material->id], array_map('intval', array_column(array_values($rows), 'attribute_id')));
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
