<?php

namespace Tests\Unit;

use App\Models\Product\Category;
use App\Models\Product\Product;
use App\Models\Product\ProductDeliveryBlock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductDeliveryBlockTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_delivery_block(): void
    {
        $block = ProductDeliveryBlock::create([
            'title' => 'Test Delivery',
            'description' => 'Test Description',
            'icon' => 'ri-test-line',
            'icon_color' => 'red-600',
            'bg_color' => 'red-100',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->assertDatabaseHas('product_delivery_blocks', [
            'id' => $block->id,
            'title' => 'Test Delivery',
            'is_active' => true,
        ]);
    }

    public function test_can_attach_delivery_block_to_category(): void
    {
        $category = Category::factory()->create();
        $block = ProductDeliveryBlock::factory()->create();

        $category->deliveryBlocks()->attach($block->id, ['sort_order' => 1]);

        $this->assertTrue($category->deliveryBlocks()->where('delivery_block_id', $block->id)->exists());
    }

    public function test_can_attach_delivery_block_to_product(): void
    {
        $product = Product::factory()->create();
        $block = ProductDeliveryBlock::factory()->create();

        $product->deliveryBlocks()->attach($block->id, ['sort_order' => 1]);

        $this->assertTrue($product->deliveryBlocks()->where('delivery_block_id', $block->id)->exists());
    }

    public function test_get_for_product_returns_product_blocks_when_override_exists(): void
    {
        $product = Product::factory()->create();
        $category = Category::factory()->create();
        $product->taxons()->attach($category->id);

        $productBlock = ProductDeliveryBlock::factory()->create();
        $categoryBlock = ProductDeliveryBlock::factory()->create();

        $product->deliveryBlocks()->attach($productBlock->id);
        $category->deliveryBlocks()->attach($categoryBlock->id);

        $result = ProductDeliveryBlock::getForProduct($product);

        $this->assertCount(1, $result);
        $this->assertEquals($productBlock->id, $result->first()->id);
    }

    public function test_get_for_product_returns_category_blocks_when_no_override(): void
    {
        $product = Product::factory()->create();
        $category = Category::factory()->create();
        $product->taxons()->attach($category->id);

        $categoryBlock = ProductDeliveryBlock::factory()->create();
        $category->deliveryBlocks()->attach($categoryBlock->id);

        $result = ProductDeliveryBlock::getForProduct($product);

        $this->assertCount(1, $result);
        $this->assertEquals($categoryBlock->id, $result->first()->id);
    }

    public function test_get_for_product_returns_empty_when_no_blocks(): void
    {
        $product = Product::factory()->create();
        $category = Category::factory()->create();
        $product->taxons()->attach($category->id);

        $result = ProductDeliveryBlock::getForProduct($product);

        $this->assertCount(0, $result);
    }

    public function test_active_scope_filters_only_active_blocks(): void
    {
        ProductDeliveryBlock::factory()->create(['is_active' => true]);
        ProductDeliveryBlock::factory()->create(['is_active' => false]);
        ProductDeliveryBlock::factory()->create(['is_active' => true]);

        $activeBlocks = ProductDeliveryBlock::active()->get();

        $this->assertCount(2, $activeBlocks);
        $this->assertTrue($activeBlocks->every(fn($block) => $block->is_active));
    }

    public function test_ordered_scope_sorts_by_sort_order(): void
    {
        $block3 = ProductDeliveryBlock::factory()->create(['sort_order' => 3]);
        $block1 = ProductDeliveryBlock::factory()->create(['sort_order' => 1]);
        $block2 = ProductDeliveryBlock::factory()->create(['sort_order' => 2]);

        $ordered = ProductDeliveryBlock::ordered()->get();

        $this->assertEquals($block1->id, $ordered[0]->id);
        $this->assertEquals($block2->id, $ordered[1]->id);
        $this->assertEquals($block3->id, $ordered[2]->id);
    }
}
