<?php

namespace Tests\Feature\Api;

use App\Actions\Product\BuildMergedProductDraftAction;
use App\Actions\Product\DetachVariantFromParentAction;
use App\Models\Product\Attribute;
use App\Models\Product\Product;
use App\Services\Product\ProductDescriptionHtml;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OfferInheritedContentTest extends TestCase
{
    public function test_multiple_values_are_one_specification_and_material_inherits_even_if_variation_enabled(): void
    {
        $parent = Product::factory()->create(['is_variable' => true]);
        $offer = Product::factory()->create(['parent_product_id' => $parent->id]);
        $material = Attribute::create(['name' => 'Материал корпуса', 'slug' => 'material_korpusa', 'type' => 'text', 'is_use_in_variations' => true, 'is_multiple' => true]);
        $parent->attributes()->attach($material->id, ['custom_value' => 'ЛДСП']);
        $filling = Attribute::create(['name' => 'Внутреннее наполнение', 'slug' => 'filling', 'type' => 'text', 'is_multiple' => true]);
        foreach (['2 горизонтальные штанги', 'Полки'] as $value) {
            $v = $filling->values()->create(['value' => $value, 'slug' => \Illuminate\Support\Str::slug($value)]);
            $parent->attributes()->attach($filling->id, ['attribute_value_id' => $v->id]);
        }
        $specs = collect($this->getJson('/api/v1/products/'.$offer->slug)->assertOk()->json('product.specifications'));
        $this->assertSame('ЛДСП', $specs->firstWhere('slug', 'material_korpusa')['value']);
        $this->assertCount(1, $specs->where('slug', 'filling'));
        $this->assertSame('2 горизонтальные штанги, Полки', $specs->firstWhere('slug', 'filling')['value']);
        $nested = collect($this->getJson('/api/v1/products/'.$parent->slug)->assertOk()->json('product.variants'))->firstWhere('id', $offer->id);
        $this->assertSame($specs->all(), $nested['specifications']);
    }

    public function test_gallery_order_is_respected_in_original_and_hd_api_images(): void
    {
        $offer = Product::factory()->create();
        foreach ([['main.jpg', 'images', 1], ['second.jpg', 'gallery', 2], ['first.jpg', 'gallery', 1]] as [$file, $collection, $order]) {
            $offer->media()->create(['collection_name' => $collection, 'name' => $file, 'file_name' => $file,
                'mime_type' => 'image/jpeg', 'disk' => 'public', 'conversions_disk' => 'public', 'size' => 1, 'manipulations' => [],
                'custom_properties' => [], 'generated_conversions' => [], 'responsive_images' => [], 'order_column' => $order]);
        }
        $offer->unsetRelation('media');
        foreach ([$offer->images_urls, $offer->images_hd_urls] as $urls) {
            $this->assertStringContainsString('main', $urls[0]);
            $this->assertStringContainsString('first', $urls[1]);
            $this->assertStringContainsString('second', $urls[2]);
        }
        $images = $this->getJson('/api/v1/products/'.$offer->slug)->assertOk()->json('product.images');
        $this->assertStringContainsString('first', $images[1]);
        $this->assertStringContainsString('second', $images[2]);
    }

    public static function emptyEditorValues(): array
    {
        return [[null], [''], [' '], ['<p></p>'], ['<p><br></p>'], ['<div><p>&nbsp;</p></div>'],
            ["<p>\u{00A0}\u{200B}\u{FEFF}</p>"], ['<!-- editor comment -->'], ['<ul><li><br></li></ul>']];
    }

    #[DataProvider('emptyEditorValues')]
    public function test_blank_legacy_html_inherits_in_direct_and_nested_api(?string $empty): void
    {
        $parent = Product::factory()->create(['is_variable' => true, 'description' => '<p>Общее описание</p>', 'state' => 'active']);
        $offer = Product::factory()->create(['parent_product_id' => $parent->id, 'state' => 'active']);
        // Reproduce legacy database rows without the new write normalizer.
        DB::table('products')->where('id', $offer->id)->update(['description' => $empty]);
        $this->getJson('/api/v1/products/'.$offer->slug)->assertOk()->assertJsonPath('product.description', $parent->description);
        $this->getJson('/api/v1/products/'.$offer->id)->assertOk()->assertJsonPath('product.description', $parent->description);
        $response = $this->getJson('/api/v1/products/'.$parent->slug)->assertOk();
        $nested = collect($response->json('product.variants'))->firstWhere('id', $offer->id);
        $this->assertSame($parent->description, $nested['description']);
        $this->assertSame('<p></p>', app(ProductDescriptionHtml::class)->forEditor($empty));
        $this->assertSame('', app(ProductDescriptionHtml::class)->sanitize($empty));
    }

    public function test_own_description_and_image_only_description_are_preserved(): void
    {
        $parent = Product::factory()->create(['is_variable' => true, 'description' => '<p>Parent</p>']);
        foreach (['<p>Own text</p>', '<p><img src="https://example.com/texture.jpg" alt="Texture"></p>', '0'] as $html) {
            $offer = Product::factory()->create(['parent_product_id' => $parent->id, 'description' => $html]);
            $this->assertSame($html, $offer->getEffectiveDescription());
            $this->getJson('/api/v1/products/'.$offer->slug)->assertOk()->assertJsonPath('product.description', $html);
        }
    }

    public function test_blank_editor_description_is_normalized_on_write(): void
    {
        $product = Product::factory()->create(['description' => '<p>&nbsp;<br></p>']);
        $this->assertNull($product->fresh()->description);
        $product->update(['description' => '<p>Real content</p>']);
        $product->update(['description' => '<p></p>']);
        $this->assertNull($product->fresh()->description);
    }

    public function test_blank_own_attribute_does_not_mask_parent_and_shared_order_is_stable(): void
    {
        $parent = Product::factory()->create(['is_variable' => true]);
        $white = Product::factory()->create(['parent_product_id' => $parent->id]);
        $other = Product::factory()->create(['parent_product_id' => $parent->id]);
        $material = Attribute::create(['name' => 'Материал корпуса', 'slug' => 'body-material', 'type' => 'text', 'is_use_in_variations' => false]);
        $filling = Attribute::create(['name' => 'Наполнение', 'slug' => 'filling', 'type' => 'text', 'is_use_in_variations' => false]);
        $color = Attribute::create(['name' => 'Цвет', 'slug' => 'color', 'type' => 'text', 'is_use_in_variations' => true]);
        $parent->attributes()->attach($material->id, ['custom_value' => 'ЛДСП']);
        $parent->attributes()->attach($filling->id, ['custom_value' => '3 секции']);
        $parent->attributes()->attach($color->id, ['custom_value' => 'Не наследовать']);
        $white->attributes()->attach($material->id, ['custom_value' => ' ']);
        $white->attributes()->attach($color->id, ['custom_value' => 'Белый']);
        $other->attributes()->attach($filling->id, ['custom_value' => '4 секции']);
        $other->attributes()->attach($material->id, ['custom_value' => 'МДФ']);
        $direct = $this->getJson('/api/v1/products/'.$white->slug)->assertOk()->json('product.specifications');
        $this->assertSame('ЛДСП', collect($direct)->firstWhere('slug', 'body-material')['value']);
        $this->assertSame('Белый', collect($direct)->firstWhere('slug', 'color')['value']);
        $otherSpecs = $other->fresh()->buildEffectiveSpecificationsForApi();
        $this->assertSame(['body-material', 'filling'], array_slice(array_column($otherSpecs, 'slug'), 0, 2));
        $this->assertSame(['МДФ', '4 секции'], array_slice(array_column($otherSpecs, 'value'), 0, 2));
        $response = $this->getJson('/api/v1/products/'.$parent->slug)->assertOk();
        $nested = collect($response->json('product.variants'))->firstWhere('id', $white->id);
        $this->assertSame($direct, $nested['specifications']);
    }

    public function test_zero_and_named_attribute_values_are_not_lost(): void
    {
        $parent = Product::factory()->create(['is_variable' => true]);
        $offer = Product::factory()->create(['parent_product_id' => $parent->id]);
        $attribute = Attribute::create(['name' => 'Количество', 'slug' => 'number', 'type' => 'text']);
        $parent->attributes()->attach($attribute->id, ['custom_value' => '12']);
        $offer->attributes()->attach($attribute->id, ['custom_value' => '0']);
        $this->assertSame('0', collect($offer->buildEffectiveSpecificationsForApi())->firstWhere('slug', 'number')['value']);
        $value = $attribute->values()->create(['value' => 'Нет', 'slug' => 'no']);
        $offer->attributes()->updateExistingPivot($attribute->id, ['custom_value' => ' ', 'attribute_value_id' => $value->id]);
        $this->assertSame('Нет', collect($offer->fresh()->buildEffectiveSpecificationsForApi())->firstWhere('slug', 'number')['value']);
    }

    public function test_detaching_materializes_the_effective_description(): void
    {
        $parent = Product::factory()->create(['is_variable' => true, 'description' => '<p>Inherited</p>']);
        $offer = Product::factory()->create(['parent_product_id' => $parent->id]);
        DB::table('products')->where('id', $offer->id)->update(['description' => '<p></p>']);
        app(DetachVariantFromParentAction::class)->execute($offer->fresh());
        $this->assertNull($offer->fresh()->parent_product_id);
        $this->assertSame('<p>Inherited</p>', $offer->fresh()->description);
    }

    public function test_merge_draft_does_not_choose_long_empty_markup_as_description(): void
    {
        $empty = Product::factory()->create();
        $real = Product::factory()->create(['description' => '<p>Real</p>']);
        DB::table('products')->where('id', $empty->id)->update(['description' => str_repeat('<p>&nbsp;</p>', 100)]);
        $draft = app(BuildMergedProductDraftAction::class)->execute(collect([$empty->fresh(), $real]));
        $this->assertSame('<p>Real</p>', $draft->description);
    }
}
