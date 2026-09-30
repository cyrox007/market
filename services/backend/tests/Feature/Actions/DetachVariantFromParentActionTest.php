<?php

namespace Tests\Feature\Actions;

use App\Actions\Product\AttachProductsToVariableProductAction;
use App\Actions\Product\Data\MergeProductsIntoVariableProductData;
use App\Actions\Product\DetachVariantFromParentAction;
use App\Actions\Product\MergeProductsIntoVariableProductAction;
use App\Models\Product\Attribute;
use App\Models\Product\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;
use Vanilo\Product\Models\ProductState;

class DetachVariantFromParentActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Attribute::ensureVariantAttribute();
    }

    public function test_detach_makes_variant_standalone_and_resyncs_parent_price(): void
    {
        $cheap = Product::factory()->create(['name' => 'Стол белый', 'price' => 3000, 'sku' => 'S-1', 'slug' => 'stol-belyi', 'external_id' => 'ext-1']);
        $dear = Product::factory()->create(['name' => 'Стол чёрный', 'price' => 8000]);
        $parent = $this->merge([$cheap->id, $dear->id]);

        $this->assertSame(3000.0, (float) $parent->fresh()->price);

        app(DetachVariantFromParentAction::class)->execute($cheap->fresh());

        $cheap->refresh();
        $this->assertNull($cheap->parent_product_id);
        $this->assertFalse((bool) $cheap->is_variable);
        $this->assertSame('S-1', $cheap->sku);
        $this->assertSame('stol-belyi', $cheap->slug);
        $this->assertSame('ext-1', $cheap->external_id);
        $this->assertSame(3000.0, (float) $cheap->price);
        $this->assertDatabaseMissing('product_variant_attributes', ['product_id' => $cheap->id]);

        $parent->refresh();
        $this->assertTrue($parent->isVariable());
        $this->assertSame(ProductState::ACTIVE, $parent->state->value());
        $this->assertSame(1, $parent->variants()->count());
        $this->assertSame(8000.0, (float) $parent->price);
    }

    public function test_detaching_last_variant_deactivates_empty_parent_but_keeps_it_variable(): void
    {
        $a = Product::factory()->create(['name' => 'Стол белый']);
        $b = Product::factory()->create(['name' => 'Стол чёрный']);
        $parent = $this->merge([$a->id, $b->id]);

        app(DetachVariantFromParentAction::class)->execute($a->fresh());
        app(DetachVariantFromParentAction::class)->execute($b->fresh());

        $parent->refresh();
        $this->assertNotNull($parent->id);
        $this->assertTrue($parent->isVariable());
        $this->assertSame(ProductState::INACTIVE, $parent->state->value());
        $this->assertNull($b->fresh()->parent_product_id);
    }

    public function test_emptied_parent_can_be_refilled_via_attach(): void
    {
        $a = Product::factory()->create(['name' => 'Стол белый']);
        $b = Product::factory()->create(['name' => 'Стол чёрный']);
        $parent = $this->merge([$a->id, $b->id]);

        app(DetachVariantFromParentAction::class)->execute($a->fresh());
        app(DetachVariantFromParentAction::class)->execute($b->fresh());

        app(AttachProductsToVariableProductAction::class)->execute($parent->fresh(), [$a->id => 'Белый']);

        $this->assertSame($parent->id, $a->fresh()->parent_product_id);
    }

    public function test_detach_is_blocked_for_group_loaded_from_one_c(): void
    {
        $parent = Product::factory()->create(['external_id' => 'ext-parent', 'is_variable' => true]);
        $variant = Product::factory()->create(['parent_product_id' => $parent->id, 'is_variable' => false]);

        $this->assertNotNull(DetachVariantFromParentAction::blockReason($variant));

        $this->expectException(InvalidArgumentException::class);
        app(DetachVariantFromParentAction::class)->execute($variant);
    }

    public function test_detach_is_blocked_for_standalone_product(): void
    {
        $product = Product::factory()->create(['parent_product_id' => null]);

        $this->assertNotNull(DetachVariantFromParentAction::blockReason($product));
    }

    public function test_detached_product_can_be_merged_again(): void
    {
        $a = Product::factory()->create(['name' => 'Стол белый']);
        $b = Product::factory()->create(['name' => 'Стол чёрный']);
        $c = Product::factory()->create(['name' => 'Стол серый']);
        $this->merge([$a->id, $b->id, $c->id]);

        app(DetachVariantFromParentAction::class)->execute($c->fresh());
        $d = Product::factory()->create(['name' => 'Стол синий']);

        $newParent = $this->merge([$c->id, $d->id]);

        $this->assertSame($newParent->id, $c->fresh()->parent_product_id);
    }

    /**
     * @param  list<int>  $productIds
     */
    protected function merge(array $productIds): Product
    {
        return app(MergeProductsIntoVariableProductAction::class)->execute(
            new MergeProductsIntoVariableProductData(productIds: $productIds),
        );
    }
}
