<?php

namespace Tests\Feature\Actions;

use App\Actions\Product\AttachProductsToVariableProductAction;
use App\Actions\Product\BuildMergedProductDraftAction;
use App\Actions\Product\Data\MergeProductsIntoVariableProductData;
use App\Actions\Product\MergeProductsIntoVariableProductAction;
use App\Models\Product\Attribute;
use App\Models\Product\Category;
use App\Models\Product\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class AttachProductsToVariableProductActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Attribute::ensureVariantAttribute();
    }

    public function test_attach_links_product_as_variant_without_rebuilding_parent(): void
    {
        $parent = $this->mergedParent();
        $parent->update(['name' => 'Стенка МАРТА-11', 'description' => 'Ручное описание']);

        $forgotten = Product::factory()->create([
            'name' => '085.001.206 Стенка МАРТА-11 Белый гладкий',
            'price' => 1000,
            'sku' => 'S-W',
            'external_id' => 'ext-white',
        ]);

        app(AttachProductsToVariableProductAction::class)->execute($parent, [$forgotten->id => '']);

        $forgotten->refresh();
        $this->assertSame($parent->id, $forgotten->parent_product_id);
        $this->assertSame('S-W', $forgotten->sku);
        $this->assertSame('ext-white', $forgotten->external_id);

        $variantAttr = Attribute::where('slug', Attribute::SLUG_VARIANT)->firstOrFail();
        $this->assertDatabaseHas('product_variant_attributes', [
            'product_id' => $forgotten->id,
            'attribute_id' => $variantAttr->id,
            'custom_value' => 'Белый гладкий',
        ]);

        $parent->refresh();
        $this->assertSame('Стенка МАРТА-11', $parent->name);
        $this->assertSame('Ручное описание', $parent->description);
        $this->assertSame(3, $parent->variants()->count());
        $this->assertSame(1000.0, (float) $parent->price);
    }

    public function test_attach_adds_product_categories_to_parent(): void
    {
        $tables = Category::factory()->create(['name' => 'Столы', 'slug' => 'stoly']);
        $wardrobes = Category::factory()->create(['name' => 'Шкафы', 'slug' => 'shkafy']);

        $parent = $this->mergedParent();
        $parent->taxons()->sync([$tables->id]);

        $product = Product::factory()->create(['name' => 'Стол серый']);
        $product->taxons()->attach([$tables->id, $wardrobes->id]);

        $action = app(AttachProductsToVariableProductAction::class);
        $this->assertSame(['Шкафы'], $action->newCategoryNames($parent, collect([$product->fresh('taxons')])));

        $result = $action->execute($parent, [$product->id => 'Серый']);

        $this->assertSame(['Шкафы'], $result['added_category_names']);
        $this->assertEqualsCanonicalizing([$tables->id, $wardrobes->id], $parent->taxons()->pluck('taxons.id')->all());
    }

    public function test_attach_rejects_label_already_used_in_group(): void
    {
        $parent = $this->mergedParent();
        $product = Product::factory()->create(['name' => 'Другой стол']);

        $this->expectException(InvalidArgumentException::class);
        app(AttachProductsToVariableProductAction::class)->execute($parent, [$product->id => 'белый']);
    }

    public function test_attach_rejects_variants_and_products_with_variants(): void
    {
        $parent = $this->mergedParent();
        $otherParent = $this->mergedParent();
        $foreignVariant = $otherParent->variants()->first();

        foreach ([$foreignVariant->id, $otherParent->id, $parent->id] as $productId) {
            try {
                app(AttachProductsToVariableProductAction::class)->execute($parent, [$productId => 'Любой']);
                $this->fail("Товар {$productId} не должен привязываться");
            } catch (InvalidArgumentException) {
                $this->assertSame($parent->variants()->count(), 2);
            }
        }
    }

    public function test_attach_rejects_non_variable_parent(): void
    {
        $simple = Product::factory()->create(['is_variable' => false]);
        $product = Product::factory()->create();

        $this->expectException(InvalidArgumentException::class);
        app(AttachProductsToVariableProductAction::class)->execute($simple, [$product->id => 'Вариант']);
    }

    public function test_suggest_label_strips_one_c_code_and_parent_name(): void
    {
        $parent = Product::factory()->make(['name' => 'Стенка МАРТА-11']);
        $builder = app(BuildMergedProductDraftAction::class);

        $this->assertSame('Белый гладкий', $builder->suggestLabelForParent(
            $parent,
            Product::factory()->make(['name' => '085.001.206 Стенка МАРТА-11 белый гладкий']),
        ));
        $this->assertSame('Шкаф СОЛО', $builder->suggestLabelForParent(
            $parent,
            Product::factory()->make(['name' => '085.006.201 Шкаф СОЛО']),
        ));
        $this->assertSame('Стенка МАРТА-110 белый', $builder->suggestLabelForParent(
            $parent,
            Product::factory()->make(['name' => '085.001.300 Стенка МАРТА-110 белый']),
        ));
    }

    /**
     * @param  list<string|null>  $externalIds
     */
    protected function mergedParent(array $externalIds = [null, null]): Product
    {
        $names = ['Стол белый', 'Стол чёрный'];
        $ids = collect($names)->map(fn (string $name, int $i) => Product::factory()->create([
            'name' => $name,
            'price' => 5000,
            'external_id' => $externalIds[$i] ?? null,
        ])->id)->all();

        return app(MergeProductsIntoVariableProductAction::class)->execute(
            new MergeProductsIntoVariableProductData(productIds: $ids),
        );
    }
}
