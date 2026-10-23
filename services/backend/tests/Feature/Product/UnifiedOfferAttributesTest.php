<?php

namespace Tests\Feature\Product;

use App\Actions\Product\BuildMergeVariantFormDataAction;
use App\Actions\Product\DetachVariantFromParentAction;
use App\Actions\Product\SyncVariantVariationAttributesAction;
use App\Models\Product\Attribute;
use App\Models\Product\Category;
use App\Models\Product\Product;
use App\Services\Product\ConsolidateProductAttributes;
use App\Services\Product\OfferAttributeOverrides;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UnifiedOfferAttributesTest extends TestCase
{
    private function attribute(string $slug, bool $variation = false): Attribute
    {
        return Attribute::create(['name' => $slug, 'slug' => $slug, 'type' => 'string',
            'allow_custom_value' => true, 'is_filterable' => true, 'is_use_in_variations' => $variation]);
    }

    public function test_modal_import_and_api_use_the_same_value_without_erasing_omitted_fields(): void
    {
        $parent = Product::factory()->create(['is_variable' => true]);
        $offer = Product::factory()->create(['parent_product_id' => $parent->id]);
        $filling = $this->attribute('filling', true);
        $size = $this->attribute('size', true);
        $offer->attributes()->attach($filling->id, ['custom_value' => 'Полки']);
        $offer->attributes()->attach($size->id, ['custom_value' => '160']);

        app(SyncVariantVariationAttributesAction::class)->execute($offer, ['variation_custom_'.$filling->id => 'Ящики'], $parent);

        $this->assertSame('Ящики', $offer->attributes()->where('slug', 'filling')->first()->pivot->custom_value);
        $this->assertSame('160', $offer->attributes()->where('slug', 'size')->first()->pivot->custom_value);
        $this->assertDatabaseCount('product_variant_attributes', 0);
        $data = app(BuildMergeVariantFormDataAction::class)->execute($parent, $offer->fresh());
        $this->assertSame('Ящики', $data['variation_custom_'.$filling->id]);
        $this->assertSame('Ящики', collect($offer->fresh()->buildEffectiveSpecificationsForApi())->firstWhere('slug', 'filling')['value']);
        $label = Attribute::ensureVariantAttribute();
        app(SyncVariantVariationAttributesAction::class)->execute($offer, ['variation_custom_'.$label->id => 'Свой вариант'], $parent);
        $rebuilt = app(BuildMergeVariantFormDataAction::class)->execute($parent, $offer->fresh());
        $this->assertSame('Свой вариант', $rebuilt['variation_custom_'.$label->id]);
    }

    public function test_direct_offer_inherits_shared_properties_preserves_overrides_and_links_siblings(): void
    {
        $category = Category::factory()->create();
        $parent = Product::factory()->create(['is_variable' => true, 'description' => '<p>Общее описание</p>']);
        $parent->taxons()->attach($category);
        $offer = Product::factory()->create(['parent_product_id' => $parent->id, 'description' => null, 'price' => 1600]);
        $sibling = Product::factory()->create(['parent_product_id' => $parent->id, 'price' => 1300]);
        $hidden = Product::factory()->create(['parent_product_id' => $parent->id, 'state' => 'inactive']);
        $material = $this->attribute('material');
        $width = $this->attribute('sirina-mm');
        $parent->attributes()->attach($material->id, ['custom_value' => 'ЛДСП']);
        $parent->attributes()->attach($width->id, ['custom_value' => '1300']);
        $offer->attributes()->attach($width->id, ['custom_value' => '1600']);

        $response = $this->getJson('/api/v1/products/'.$offer->slug)->assertOk()
            ->assertJsonPath('product.id', $offer->id)->assertJsonPath('product.sku', $offer->sku)
            ->assertJsonPath('product.description', '<p>Общее описание</p>')->assertJsonPath('product.price', 1600);
        $specs = collect($response->json('product.specifications'))->keyBy('slug');
        $this->assertSame('ЛДСП', $specs['material']['value']);
        $this->assertSame('1600', $specs['sirina-mm']['value']);
        $siblings = collect($response->json('product.variants'))->keyBy('id');
        $this->assertSame('/product/'.$sibling->slug, $siblings[$sibling->id]['url']);
        $this->assertFalse($siblings->has($hidden->id));
        $this->assertSame($category->slug, $response->json('product.category.slug'));
        $offer->update(['description' => '<p>Своё описание</p>']);
        $this->getJson('/api/v1/products/'.$offer->slug)->assertOk()->assertJsonPath('product.description', '<p>Своё описание</p>');
    }

    public function test_optional_overrides_do_not_erase_variation_parameters_and_clear_to_inheritance(): void
    {
        $parent = Product::factory()->create(['is_variable' => true]);
        $offer = Product::factory()->create(['parent_product_id' => $parent->id]);
        $material = $this->attribute('material');
        $size = $this->attribute('size', true);
        $parent->attributes()->attach($material->id, ['custom_value' => 'Дерево']);
        $offer->attributes()->attach($size->id, ['custom_value' => '160']);
        $service = app(OfferAttributeOverrides::class);
        $service->save($offer, $parent, [['attribute_id' => $material->id, 'custom_value' => 'Металл']]);
        $this->assertSame('Металл', $service->formData($offer->fresh(), $parent)[0]['custom_value']);
        $service->save($offer, $parent, []);
        $this->assertSame('160', $offer->attributes()->where('slug', 'size')->first()->pivot->custom_value);
        $this->assertSame('Дерево', collect($offer->fresh()->buildEffectiveSpecificationsForApi())->firstWhere('slug', 'material')['value']);
        app(DetachVariantFromParentAction::class)->execute($offer->fresh());
        $this->assertDatabaseHas('product_product_attributes', ['product_id' => $offer->id, 'attribute_id' => $size->id, 'custom_value' => '160']);
        $this->assertDatabaseHas('product_product_attributes', ['product_id' => $offer->id, 'attribute_id' => $material->id, 'custom_value' => 'Дерево']);
    }

    public function test_consolidation_is_additive_idempotent_and_keeps_the_source_archive(): void
    {
        $product = Product::factory()->create();
        $attribute = $this->attribute('legacy');
        DB::table('product_variant_attributes')->insert(['product_id' => $product->id, 'attribute_id' => $attribute->id,
            'custom_value' => 'Старое значение', 'created_at' => now(), 'updated_at' => now()]);
        $service = app(ConsolidateProductAttributes::class);
        $this->assertSame(1, $service->execute());
        $this->assertSame(0, $service->execute());
        $this->assertDatabaseCount('product_variant_attributes', 1);
        $this->assertDatabaseHas('product_product_attributes', ['product_id' => $product->id, 'custom_value' => 'Старое значение']);
    }

    public function test_conflicting_sources_abort_before_any_copy_or_overwrite(): void
    {
        $product = Product::factory()->create();
        $attribute = $this->attribute('conflict');
        $product->attributes()->attach($attribute->id, ['custom_value' => 'Полки']);
        DB::table('product_variant_attributes')->insert(['product_id' => $product->id, 'attribute_id' => $attribute->id, 'custom_value' => 'Ящики']);
        try {
            app(ConsolidateProductAttributes::class)->execute();
            $this->fail('Conflicting data must not be selected silently.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('product_id='.$product->id, $error->getMessage());
        }
        $this->assertDatabaseHas('product_product_attributes', ['product_id' => $product->id, 'custom_value' => 'Полки']);
        $this->assertDatabaseHas('product_variant_attributes', ['product_id' => $product->id, 'custom_value' => 'Ящики']);
    }

    public function test_invalid_value_from_another_attribute_does_not_erase_existing_values(): void
    {
        $parent = Product::factory()->create(['is_variable' => true]);
        $offer = Product::factory()->create(['parent_product_id' => $parent->id]);
        $size = $this->attribute('size', true);
        $color = $this->attribute('color', true);
        $red = $color->values()->create(['value' => 'Красный', 'slug' => 'red']);
        $offer->attributes()->attach($size->id, ['custom_value' => '160']);
        try {
            app(SyncVariantVariationAttributesAction::class)->execute($offer, ['variation_attr_'.$size->id => $red->id], $parent);
            $this->fail('A value from another attribute must not be accepted.');
        } catch (\Illuminate\Validation\ValidationException $error) {
            $this->assertArrayHasKey('variation_attr_'.$size->id, $error->errors());
        }
        $this->assertDatabaseHas('product_product_attributes', ['product_id' => $offer->id, 'attribute_id' => $size->id, 'custom_value' => '160']);
    }

    public function test_audit_command_reports_conflicts_without_writing_data(): void
    {
        $product = Product::factory()->create();
        $attribute = $this->attribute('audit');
        $product->attributes()->attach($attribute->id, ['custom_value' => 'Первое']);
        DB::table('product_variant_attributes')->insert(['product_id' => $product->id, 'attribute_id' => $attribute->id, 'custom_value' => 'Второе']);
        $this->artisan('catalog:attribute-storage-audit')->assertExitCode(1);
        $this->assertDatabaseCount('product_product_attributes', 1);
        $this->assertDatabaseHas('product_product_attributes', ['product_id' => $product->id, 'custom_value' => 'Первое']);
    }

    public function test_offer_photos_fall_back_to_parent_and_own_gallery_has_priority(): void
    {
        $parent = Product::factory()->create(['is_variable' => true]);
        $offer = Product::factory()->create(['parent_product_id' => $parent->id]);
        $media = fn (Product $product, string $file) => $product->media()->create([
            'collection_name' => 'images', 'name' => $file, 'file_name' => $file,
            'mime_type' => 'image/jpeg', 'disk' => 'public', 'conversions_disk' => 'public', 'size' => 100,
            'manipulations' => [], 'custom_properties' => [], 'generated_conversions' => [], 'responsive_images' => [],
        ]);
        $parentImage = $media($parent, 'parent.jpg');
        $this->getJson('/api/v1/products/'.$offer->slug)->assertOk()
            ->assertJsonPath('product.main_image', $parentImage->getUrl('hd'));
        $ownImage = $media($offer, 'offer.jpg');
        $this->getJson('/api/v1/products/'.$offer->slug)->assertOk()
            ->assertJsonPath('product.main_image', $ownImage->getUrl('hd'));
    }
}
