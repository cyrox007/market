<?php

namespace Tests\Feature\Api;

use App\Models\Product\Product;
use App\Models\Product\ProductCollection;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CollectionRelatedProductsTest extends TestCase
{
    public function test_related_group_uses_active_offer_photo_and_original_when_thumbnail_is_not_generated(): void
    {
        $owner = Product::factory()->create(['state' => 'active']);
        $group = Product::factory()->create(['state' => 'active', 'is_variable' => true]);
        $hidden = Product::factory()->create(['state' => 'draft', 'parent_product_id' => $group->id]);
        $offer = Product::factory()->create(['state' => 'active', 'parent_product_id' => $group->id]);
        $photo = function (Product $product, string $name) {
            return $product->media()->create([
                'collection_name' => 'images', 'name' => $name, 'file_name' => $name.'.jpg',
                'mime_type' => 'image/jpeg', 'disk' => 'public', 'size' => 100,
                'manipulations' => [], 'custom_properties' => [], 'generated_conversions' => [],
                'responsive_images' => [],
            ]);
        };
        $photo($hidden, 'hidden');
        $image = $photo($offer, 'visible');
        $owner->attachRelatedProduct($group);

        foreach (['/api/v1/products/'.$owner->id.'/related', '/api/v1/products/'.$owner->slug] as $url) {
            $response = $this->getJson($url)->assertOk();
            $prefix = str_ends_with($url, '/related') ? 'data.0' : 'related.data.0';
            $response->assertJsonPath($prefix.'.id', $group->id)
                ->assertJsonPath($prefix.'.image', $image->getUrl())
                ->assertJsonPath($prefix.'.thumbnail', $image->getUrl());
        }

        $ownImage = $photo($group, 'group');
        Product::flushAllProductCaches();
        $this->getJson('/api/v1/products/'.$owner->id.'/related')->assertOk()
            ->assertJsonPath('data.0.image', $ownImage->getUrl())
            ->assertJsonPath('data.0.thumbnail', $ownImage->getUrl());
    }

    private function series(array $products, array $options = []): ProductCollection
    {
        $collection = ProductCollection::create(array_merge([
            'name' => 'Бруно',
            'slug' => 'bruno-'.uniqid(),
            'is_auto' => false,
            'scope_type' => null,
            'is_active' => true,
            'recommend_collection_members' => true,
            'priority' => 0,
            'limit' => 12,
        ], $options));

        foreach ($products as $index => $product) {
            $collection->products()->attach($product->id, ['sort_order' => $index]);
        }
        $collection->flushHomeCaches();

        return $collection;
    }

    private function relatedIds(Product $product): array
    {
        return collect($this->getJson('/api/v1/products/'.$product->id.'/related')
            ->assertOk()->json('data'))->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    public function test_enabling_series_recommends_more_than_four_parent_cards_bidirectionally(): void
    {
        $items = collect(range(1, 8))->map(fn ($i) => Product::factory()->create([
            'name' => 'Бруно '.$i, 'state' => 'active', 'parent_product_id' => null,
        ]));
        $collection = $this->series($items->all(), ['recommend_collection_members' => false]);
        $root = $items->first();
        $unrelated = Product::factory()->create(['state' => 'active']);

        // The opt-in switch is OFF by default, so the series rule is not active.
        $this->assertFalse((bool) $collection->recommend_collection_members);
        $collection->update(['recommend_collection_members' => true]);
        $this->assertSame($items->skip(1)->pluck('id')->all(), $this->relatedIds($root));
        $this->assertContains($root->id, $this->relatedIds($items->last()));
        $this->assertNotContains($unrelated->id, $this->relatedIds($root));

        // Product detail uses the same related.data API response as the dedicated route.
        $response = $this->getJson('/api/v1/products/'.$root->slug)->assertOk();
        $this->assertSame($items->skip(1)->pluck('id')->all(),
            collect($response->json('related.data'))->pluck('id')->all());
    }

    public function test_variation_does_not_become_an_independent_related_card(): void
    {
        $wardrobe = Product::factory()->create(['state' => 'active', 'is_variable' => true]);
        $white = Product::factory()->create([
            'state' => 'active', 'parent_product_id' => $wardrobe->id,
        ]);
        $graphite = Product::factory()->create([
            'state' => 'active', 'parent_product_id' => $wardrobe->id,
        ]);
        $mezzanine = Product::factory()->create(['state' => 'active']);
        $this->series([$wardrobe, $mezzanine]);

        $this->assertSame([$mezzanine->id], $this->relatedIds($wardrobe));
        $this->assertSame([$mezzanine->id], $this->relatedIds($white));
        $this->assertSame([$mezzanine->id], $this->relatedIds($graphite));
        $this->assertSame([$wardrobe->id], $this->relatedIds($mezzanine));
    }

    public function test_manual_off_and_legacy_modes_override_bulk_series_rules(): void
    {
        $root = Product::factory()->create(['state' => 'active']);
        $other = Product::factory()->create(['state' => 'active']);
        $manual = Product::factory()->create(['state' => 'active']);
        $this->series([$root, $other]);

        $root->attachRelatedProduct($manual);
        // A furniture series ENHANCES the existing related-products list.
        // Manual assignments stay first and are visible beside the series.
        $this->assertSame([$manual->id, $other->id], $this->relatedIds($root));

        $rows = app(\App\Services\Product\RelatedProductsResolver::class)->forProduct($root);
        $this->assertSame(['manual', 'series'], $rows->pluck('source')->all());
        $this->assertSame('Бруно', $rows[1]['series_name']);

        $root->forceFill(['related_products_mode' => 'off'])->save();
        $this->assertSame([], $this->relatedIds($root));

        $root->forceFill(['related_products_mode' => 'manual'])->save();
        $this->assertSame([$manual->id], $this->relatedIds($root));

        $root->forceFill(['related_products_mode' => 'automatic'])->save();
        $this->assertSame([$other->id], $this->relatedIds($root));

        // A different product is not switched to manual by the root's rule.
        $this->assertContains($root->id, $this->relatedIds($other));
    }

    public function test_inactive_and_system_collections_do_not_activate_series_and_membership_invalidates_cache(): void
    {
        $first = Product::factory()->create(['state' => 'active']);
        $second = Product::factory()->create(['state' => 'active']);
        $third = Product::factory()->create(['state' => 'active']);
        $this->series([$first, $second], ['is_active' => false]);
        $system = $this->series([$first, $third], [
            'slug' => 'test-featured',
            'is_auto' => true,
            'scope_type' => 'featured',
        ]);

        $service = app(\App\Services\Catalog\CollectionRelatedProducts::class);
        $this->assertSame([], $service->forProduct($first, [])->pluck('id')->all());

        $series = $this->series([$first, $second]);
        $this->assertSame([$second->id], $this->relatedIds($first));

        $series->products()->attach($third->id, ['sort_order' => 10]);
        $series->flushHomeCaches();
        $this->assertSame([$second->id, $third->id], $this->relatedIds($first));

        $series->products()->detach($second->id);
        $series->flushHomeCaches();
        $this->assertSame([$third->id], $this->relatedIds($first));

        $series->update(['recommend_collection_members' => false]);
        $this->assertSame([], $service->forProduct($first, [])->pluck('id')->all());
    }

    public function test_overlapping_series_are_deduplicated_and_limit_is_enforced(): void
    {
        $root = Product::factory()->create(['state' => 'active']);
        $shared = Product::factory()->create(['state' => 'active']);
        $first = Product::factory()->create(['state' => 'active']);
        $second = Product::factory()->create(['state' => 'active']);
        $this->series([$root, $first, $shared], ['priority' => 0]);
        $this->series([$root, $shared, $second], ['priority' => 5]);

        $this->assertSame([$first->id, $shared->id, $second->id], $this->relatedIds($root));

        $extras = collect(range(1, 30))->map(fn () => Product::factory()->create(['state' => 'active']));
        $largeSeries = $this->series([$root, ...$extras->all()], ['priority' => -5]);
        $ids = $this->relatedIds($root);
        $this->assertCount(\App\Services\Catalog\CollectionRelatedProducts::MAX_PRODUCTS, $ids);
        $this->assertSame($extras->take(24)->pluck('id')->all(), $ids);
    }

    public function test_series_and_manual_relations_are_deduplicated_and_admin_and_api_share_resolver(): void
    {
        $root = Product::factory()->create(['state' => 'active']);
        $member = Product::factory()->create(['state' => 'active']);
        $additional = Product::factory()->create(['state' => 'active']);
        $this->series([$root, $member, $additional]);
        $root->attachRelatedProduct($member);

        $resolved = app(\App\Services\Product\RelatedProductsResolver::class)->forProduct($root);
        $this->assertSame([$member->id, $additional->id], $resolved->pluck('product.id')->all());
        $this->assertSame(['manual', 'series'], $resolved->pluck('source')->all());
        $this->assertSame($resolved->pluck('product.id')->all(), $this->relatedIds($root));

        $root->forceFill(['related_products_mode' => 'manual'])->save();
        $this->assertSame([$member->id], $this->relatedIds($root));

        $root->forceFill(['related_products_mode' => 'automatic'])->save();
        $this->assertSame([$member->id, $additional->id], $this->relatedIds($root));
        $this->assertSame(['series', 'series'],
            app(\App\Services\Product\RelatedProductsResolver::class)->forProduct($root)->pluck('source')->all());
    }
}
