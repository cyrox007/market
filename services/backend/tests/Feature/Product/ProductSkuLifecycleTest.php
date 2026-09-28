<?php

namespace Tests\Feature\Product;

use App\Models\Product\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductSkuLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_product_can_become_variable_parent_with_null_sku(): void
    {
        $product = Product::factory()->create([
            'sku' => 'OLD-SKU-001',
            'is_variable' => false,
            'parent_product_id' => null,
        ]);

        $product->update([
            'is_variable' => true,
            'sku' => null,
        ]);

        $this->assertNull($product->fresh()->sku);
    }

    public function test_new_variable_parent_keeps_null_sku(): void
    {
        $product = Product::factory()->create([
            'sku' => null,
            'is_variable' => true,
            'parent_product_id' => null,
        ]);

        $this->assertNull($product->fresh()->sku);
    }

    public function test_new_simple_product_without_sku_falls_back_to_id(): void
    {
        $product = Product::factory()->create([
            'sku' => null,
            'is_variable' => false,
            'parent_product_id' => null,
        ]);

        $this->assertSame((string) $product->id, $product->fresh()->sku);
    }

    public function test_existing_simple_product_cleared_sku_falls_back_to_id(): void
    {
        $product = Product::factory()->create([
            'sku' => 'SKU-TO-CLEAR',
            'is_variable' => false,
            'parent_product_id' => null,
        ]);

        $product->update(['sku' => null]);

        $this->assertSame((string) $product->id, $product->fresh()->sku);
    }

    public function test_existing_sku_is_not_erased_just_by_enabling_variability(): void
    {
        $product = Product::factory()->create([
            'sku' => 'KEEP-ME',
            'is_variable' => false,
            'parent_product_id' => null,
        ]);

        $product->update(['is_variable' => true]);

        $this->assertSame('KEEP-ME', $product->fresh()->sku);
    }
}
