<?php

namespace Tests\Feature\Admin;

use App\Actions\Product\Data\MergeProductsIntoVariableProductData;
use App\Actions\Product\MergeProductsIntoVariableProductAction;
use App\Filament\Resources\Colors\Pages\ManageBaseColors;
use App\Filament\Resources\Colors\Pages\ManageColorElements;
use App\Filament\Resources\Colors\Pages\ManageColorSolutions;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Products\RelationManagers\ProductVariantsRelationManager;
use App\Models\Product\Attribute;
use App\Models\Product\Color\BaseColor;
use App\Models\Product\Color\ColorElement;
use App\Models\Product\Color\ColorSolution;
use App\Models\Product\Product;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProductColorRowsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('super_admin', 'web');
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin_sv'));
    }

    public function test_dictionaries_are_seeded_and_pages_open(): void
    {
        $this->assertTrue(BaseColor::where('name', 'Белый')->where('hex', '#FFFFFF')->exists());
        $this->assertSame(['Фасад', 'Столешница', 'Корпус'], ColorElement::orderBy('sort_order')->pluck('name')->all());

        Livewire::test(ManageBaseColors::class)->assertSee('Чёрный');
        Livewire::test(ManageColorElements::class)->assertSee('Столешница');
        Livewire::test(ManageColorSolutions::class)->assertOk();
    }

    public function test_solution_is_created_with_base_colors(): void
    {
        $white = BaseColor::where('name', 'Белый')->sole();
        $brown = BaseColor::where('name', 'Коричневый')->sole();

        Livewire::test(ManageColorSolutions::class)
            ->callAction('create', data: ['name' => 'Дуб Вотан/Белый', 'baseColors' => [$white->id, $brown->id]])
            ->assertHasNoActionErrors();

        $solution = ColorSolution::sole();
        $this->assertSame('dub-votan-belyi', $solution->slug);
        $this->assertEqualsCanonicalizing([$white->id, $brown->id], $solution->baseColors->pluck('id')->all());
    }

    public function test_icon_change_flushes_catalog_cache(): void
    {
        $solution = ColorSolution::create(['name' => 'Дуб']);
        $revision = Cache::get('catalog_filters_revision');

        $solution->addMedia(UploadedFile::fake()->image('oak.png', 20, 20))->toMediaCollection('icon');

        $this->assertNotSame($revision, Cache::get('catalog_filters_revision'));
    }

    public function test_products_list_filters_cards_by_color_solution(): void
    {
        $white = ColorSolution::create(['name' => 'Белый']);
        $simple = Product::factory()->create(['is_variable' => false]);
        $simple->colorRows()->create(['color_element_id' => null, 'color_solution_id' => $white->id]);
        $parent = Product::factory()->create(['is_variable' => true]);
        $variant = Product::factory()->create(['parent_product_id' => $parent->id]);
        $variant->colorRows()->create(['color_element_id' => null, 'color_solution_id' => $white->id]);
        $other = Product::factory()->create(['is_variable' => false]);

        Livewire::test(ListProducts::class)
            ->filterTable('color_solution', $white->id)
            ->assertCanSeeTableRecords([$simple, $parent])
            ->assertCanNotSeeTableRecords([$other]);

        $facade = ColorElement::where('name', 'Фасад')->sole();
        $variant->colorRows()->create(['color_element_id' => $facade->id, 'color_solution_id' => $white->id]);
        Livewire::test(ListProducts::class)
            ->filterTable('color_element', $facade->id)
            ->assertCanSeeTableRecords([$parent])
            ->assertCanNotSeeTableRecords([$simple, $other]);
    }

    public function test_slug_is_generated_when_empty_and_can_be_edited(): void
    {
        $page = Livewire::test(ManageColorElements::class)
            ->callAction('create', data: ['name' => 'Ручки', 'slug' => ''])
            ->assertHasNoActionErrors();
        $element = ColorElement::where('name', 'Ручки')->sole();
        $this->assertSame('rucki', $element->slug);

        $page->callTableAction('edit', $element, data: ['name' => 'Ручки', 'slug' => 'Handles 2'])->assertHasNoTableActionErrors();
        $this->assertSame('handles-2', $element->fresh()->slug);

        $page->callTableAction('edit', $element, data: ['name' => 'Ручки', 'slug' => 'fasad'])->assertHasTableActionErrors(['slug']);

        Livewire::test(ManageBaseColors::class)->assertTableColumnExists('slug');
        Livewire::test(ManageColorSolutions::class)->assertTableColumnExists('slug');
    }

    public function test_simple_product_saves_main_and_element_colors(): void
    {
        $product = Product::factory()->create(['is_variable' => false]);
        $oak = ColorSolution::create(['name' => 'Дуб Вотан']);
        $white = ColorSolution::create(['name' => 'Белый']);
        $facade = ColorElement::where('name', 'Фасад')->sole();
        $undoRepeaterFake = Repeater::fake();

        Livewire::test(EditProduct::class, ['record' => $product->id])
            ->fillForm(['colorRows' => [
                ['color_element_id' => 'main', 'color_solution_id' => $oak->id],
                ['color_element_id' => $facade->id, 'color_solution_id' => $white->id],
            ]])
            ->call('save')
            ->assertHasNoFormErrors();

        $undoRepeaterFake();

        $rows = $product->colorRows()->get();
        $this->assertCount(2, $rows);
        $this->assertNull($rows[0]->color_element_id);
        $this->assertSame($oak->id, $rows[0]->color_solution_id);
        $this->assertSame($facade->id, $rows[1]->color_element_id);
        $this->assertSame($white->id, $rows[1]->color_solution_id);

        // Строка без элемента открывается в форме как «Основной цвет».
        $elements = collect(Livewire::test(EditProduct::class, ['record' => $product->id])->get('data.colorRows'))->pluck('color_element_id');
        $this->assertSame(['main', $facade->id], $elements->map(fn ($id) => is_numeric($id) ? (int) $id : $id)->values()->all());
    }

    public function test_same_element_twice_is_rejected(): void
    {
        $product = Product::factory()->create(['is_variable' => false]);
        $oak = ColorSolution::create(['name' => 'Дуб Вотан']);
        $white = ColorSolution::create(['name' => 'Белый']);
        $undoRepeaterFake = Repeater::fake();

        Livewire::test(EditProduct::class, ['record' => $product->id])
            ->fillForm(['colorRows' => [
                ['color_element_id' => 'main', 'color_solution_id' => $oak->id],
                ['color_element_id' => 'main', 'color_solution_id' => $white->id],
            ]])
            ->call('save')
            ->assertHasFormErrors(['colorRows'])
            ->assertSee('Основной цвет выбран в нескольких строках. У товара может быть только один основной цвет — оставьте одну строку.');

        $undoRepeaterFake();

        $this->assertSame(0, $product->colorRows()->count());
    }

    public function test_solution_and_element_are_created_from_product_form(): void
    {
        $product = Product::factory()->create(['is_variable' => false]);
        $product->colorRows()->create(['color_element_id' => null, 'color_solution_id' => ColorSolution::create(['name' => 'Белый'])->id]);
        $black = BaseColor::where('name', 'Чёрный')->sole();

        $page = Livewire::test(EditProduct::class, ['record' => $product->id]);
        $field = fn (string $name) => Str::after(collect($page->instance()->getSchema('form')->getFlatComponents())
            ->first(fn ($component): bool => $component instanceof Select && $component->getName() === $name)
            ->getKey(), 'form.');

        $page->callAction(TestAction::make('createOption')->schemaComponent($field('color_solution_id'), 'form'), ['name' => 'Графит', 'baseColors' => [$black->id]])
            ->assertHasNoActionErrors();
        $page->callAction(TestAction::make('createOption')->schemaComponent($field('color_element_id'), 'form'), ['name' => 'Ручки'])
            ->assertHasNoActionErrors();

        $this->assertSame([$black->id], ColorSolution::where('name', 'Графит')->sole()->baseColors->pluck('id')->all());
        $this->assertTrue(ColorElement::where('name', 'Ручки')->exists());
    }

    public function test_variant_saves_its_own_colors(): void
    {
        $a = Product::factory()->create(['name' => 'Стол белый']);
        $b = Product::factory()->create(['name' => 'Стол чёрный']);
        $parent = app(MergeProductsIntoVariableProductAction::class)->execute(
            new MergeProductsIntoVariableProductData(productIds: [$a->id, $b->id]),
        );
        $offer = $a->fresh();
        $white = ColorSolution::create(['name' => 'Белый']);
        $variant = Attribute::ensureVariantAttribute();
        $undoRepeaterFake = Repeater::fake();

        Livewire::test(ProductVariantsRelationManager::class, ['ownerRecord' => $parent, 'pageClass' => EditProduct::class])
            ->callTableAction('edit', $offer, data: [
                'name' => $offer->name,
                'sku' => $offer->sku,
                'price' => 1000,
                'stock' => 1,
                'state' => 'active',
                'variation_custom_'.$variant->id => 'Большой',
                'colorRows' => [['color_element_id' => 'main', 'color_solution_id' => $white->id]],
            ])
            ->assertHasNoTableActionErrors();

        $undoRepeaterFake();

        $this->assertSame($white->id, $offer->colorRows()->sole()->color_solution_id);
        $this->assertSame(0, $parent->colorRows()->count());

        Livewire::test(ProductVariantsRelationManager::class, ['ownerRecord' => $parent, 'pageClass' => EditProduct::class])
            ->assertSee('Цвет: Белый')
            ->assertSee($variant->name.': Большой');
    }
}
