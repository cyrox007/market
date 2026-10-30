<?php

namespace Tests\Feature\Api;

use App\Models\Product\Product;
use App\Services\Product\BulkRelatedProducts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BulkRelatedProductsTest extends TestCase
{
    use RefreshDatabase;

    private function links(Product $product): array
    {
        return $product->relatedProducts()->pluck('products.id')->map(fn ($id) => (int) $id)->all();
    }

    public function test_mass_add_is_symmetric_idempotent_and_does_not_include_offers(): void
    {
        $wardrobe = Product::factory()->create(['name' => 'Шкаф Бруно']);
        $mezzanine = Product::factory()->create(['name' => 'Антресоль Бруно']);
        $commode = Product::factory()->create(['name' => 'Комод Бруно']);
        $offer = Product::factory()->create(['name' => 'Шкаф Бруно белый', 'parent_product_id' => $wardrobe->id]);

        $service = app(BulkRelatedProducts::class);
        $first = $service->apply([$wardrobe->id, $mezzanine->id], [$commode->id], 'add');
        $second = $service->apply([$wardrobe->id, $mezzanine->id], [$commode->id], 'add');

        $this->assertSame(2, $first['added']);
        $this->assertSame(0, $second['added']);
        $this->assertSame([$commode->id], $this->links($wardrobe));
        $this->assertSame([$commode->id], $this->links($mezzanine));
        $this->assertEqualsCanonicalizing([$wardrobe->id, $mezzanine->id], $this->links($commode));
        $this->assertSame([], $this->links($offer));
        $this->assertDatabaseCount('product_related_products', 4);
    }

    public function test_replace_only_selected_cards_and_their_reverse_edges(): void
    {
        [$wardrobe, $mezzanine, $commode, $desk, $chair] =
            Product::factory()->count(5)->create()->all();

        $wardrobe->attachRelatedProduct($commode);
        $wardrobe->attachRelatedProduct($desk);
        $mezzanine->attachRelatedProduct($commode);
        $desk->attachRelatedProduct($chair); // unrelated to our operation

        $result = app(BulkRelatedProducts::class)->apply(
            [$wardrobe->id, $mezzanine->id], [$desk->id], 'replace'
        );

        $this->assertSame(1, $result['added']);
        $this->assertSame(2, $result['removed']);
        $this->assertSame([$desk->id], $this->links($wardrobe));
        $this->assertSame([$desk->id], $this->links($mezzanine));
        $this->assertSame([], $this->links($commode));
        $this->assertEqualsCanonicalizing(
            [$wardrobe->id, $mezzanine->id, $chair->id], $this->links($desk)
        );
        $this->assertSame([$desk->id], $this->links($chair));
    }

    public function test_remove_only_selected_pairs_and_preserves_unrelated_recommendations(): void
    {
        [$wardrobe, $mezzanine, $commode, $desk] = Product::factory()->count(4)->create()->all();
        $wardrobe->attachRelatedProduct($mezzanine);
        $wardrobe->attachRelatedProduct($commode);
        $commode->attachRelatedProduct($desk);

        $result = app(BulkRelatedProducts::class)->apply([$wardrobe->id], [$commode->id], 'remove');

        $this->assertSame(1, $result['removed']);
        $this->assertSame([$mezzanine->id], $this->links($wardrobe));
        $this->assertSame([$wardrobe->id], $this->links($mezzanine));
        $this->assertSame([$desk->id], $this->links($commode));
    }

    public function test_overlapping_source_and_target_sets_skip_self_without_dropping_shared_pair(): void
    {
        [$a, $b, $c] = Product::factory()->count(3)->create()->all();
        $a->attachRelatedProduct($c);
        $b->attachRelatedProduct($c);

        app(BulkRelatedProducts::class)->apply([$a->id, $b->id], [$b->id], 'replace');
        $this->assertSame([$b->id], $this->links($a));
        $this->assertSame([$a->id], $this->links($b));
        $this->assertSame([], $this->links($c));
        $this->assertDatabaseCount('product_related_products', 2);
    }

    public function test_rejects_inactive_targets_offers_and_unknown_ids_without_partial_changes(): void
    {
        $a = Product::factory()->create();
        $b = Product::factory()->create(['state' => 'inactive']);
        $c = Product::factory()->create(['parent_product_id' => $a->id]);

        foreach ([$b->id, $c->id, 987654321] as $invalid) {
            try {
                app(BulkRelatedProducts::class)->apply([$a->id], [$invalid], 'add');
                $this->fail('Must validate target root and state before modifying anything');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('target_product_ids', $exception->errors());
            }
        }
        $this->assertDatabaseCount('product_related_products', 0);
    }

    public function test_does_not_guess_series_by_name(): void
    {
        $a = Product::factory()->create(['name' => 'Шкаф Бруно']);
        $b = Product::factory()->create(['name' => 'Антресоль Бруно']);
        app(BulkRelatedProducts::class)->apply([$a->id], [$b->id], 'add');

        $this->assertSame([$b->id], $this->links($a));
        $this->assertSame([$a->id], $this->links($b));

        $later = Product::factory()->create(['name' => 'Комод Бруно']);
        $this->assertSame([], $this->links($later));
    }
}
