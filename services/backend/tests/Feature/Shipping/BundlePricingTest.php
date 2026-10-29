<?php

namespace Tests\Feature\Shipping;

use App\Models\Product\Product;
use App\Services\Product\BundlePricing;
use App\Services\Product\SellableBundle;
use Tests\TestCase;

class BundlePricingTest extends TestCase
{
    private function fixture(): array
    {
        $root = Product::factory()->create(['price' => 1500, 'is_sellable_bundle' => true]);
        $part = Product::factory()->create(['price' => 700]);
        $root->sellableBundleComponents()->attach($part->id, ['quantity' => 2]);

        return [$root, $part];
    }

    public function test_empty_price_uses_current_components_and_fixed_price_takes_priority(): void
    {
        [$root, $part] = $this->fixture();
        $this->assertEquals(2900, app(SellableBundle::class)->quote($root->id, null)['subtotal']);
        $part->update(['price' => 800]);
        $this->assertEquals(3100, app(SellableBundle::class)->quote($root->id, null)['subtotal']);
        $root->update(['bundle_price' => 2000.01]);
        $this->assertEquals(6000.03, app(SellableBundle::class)->quote($root->id, null, 3)['subtotal']);
        $root->update(['bundle_price' => null]);
        $this->assertEquals(3100, app(SellableBundle::class)->quote($root->id, null)['subtotal']);
    }

    public function test_allocation_preserves_quantities_and_every_kopeck_even_when_units_do_not_divide(): void
    {
        [$root, $part] = $this->fixture();
        $root->update(['bundle_price' => 1000.01]);
        $composition = app(SellableBundle::class)->composition($root->id, null, 3);
        $lines = [['product_id' => $root->id, 'quantity' => 3, 'price' => 1500],
            ['product_id' => $part->id, 'quantity' => 6, 'price' => 700]];
        $allocated = app(BundlePricing::class)->apply($composition, $lines);
        $this->assertSame(300003, array_sum(array_map(fn ($row) => (int) round($row['price'] * 100) * $row['quantity'], $allocated)));
        $quantities = collect($allocated)->groupBy('product_id')->map(fn ($rows) => $rows->sum('quantity'));
        $this->assertSame(3, $quantities[$root->id]);
        $this->assertSame(6, $quantities[$part->id]);
        $this->assertGreaterThan(2, count($allocated));
        $this->assertSame($allocated, app(BundlePricing::class)->apply($composition, $lines));
    }

    public function test_zero_price_is_not_mistaken_for_an_empty_field(): void
    {
        [$root, $part] = $this->fixture();
        $root->update(['bundle_price' => 0]);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(SellableBundle::class)->quote($root->id, null);
    }

    public function test_simple_bundle_api_displays_whole_set_price_without_changing_physical_sku_price(): void
    {
        [$root] = $this->fixture();
        $root->update(['bundle_price' => 2000.01]);
        $row = collect($this->getJson('/api/v1/products')->assertOk()->json('data'))->firstWhere('id', $root->id);
        $this->assertEquals(2000.01, $row['price']);
        $this->getJson('/api/v1/products/'.$root->slug)->assertOk()->assertJsonPath('product.price', 2000.01);
        $this->assertEquals(1500, $root->fresh()->price);
    }

    public function test_zero_base_components_can_be_distributed(): void
    {
        [$root, $part] = $this->fixture();
        $root->update(['bundle_price' => 1.01, 'price' => 0]);
        $part->update(['price' => 0]);
        $this->assertEquals(1.01, app(SellableBundle::class)->quote($root->id, null)['subtotal']);
    }
}
