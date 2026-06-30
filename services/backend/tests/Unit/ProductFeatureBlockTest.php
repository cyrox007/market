<?php

namespace Tests\Unit;

use App\Models\Product\Category;
use App\Models\Product\Product;
use App\Models\Product\ProductFeatureBlock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductFeatureBlockTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_feature_block(): void
    {
        $block = ProductFeatureBlock::create([
            'title' => 'Test Feature',
            'subtitle' => 'Test Subtitle',
            'icon' => 'ri-test-line',
            'icon_color' => 'red-600',
            'bg_color' => 'red-100',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->assertDatabaseHas('product_feature_blocks', [
            'id' => $block->id,
            'title' => 'Test Feature',
            'is_active' => true,
        ]);
    }

    public function test_can_attach_feature_block_to_category(): void
    {
        $category = Category::factory()->create();
        $block = ProductFeatureBlock::factory()->create();

        $category->featureBlocks()->attach($block->id, ['sort_order' => 1]);

        $this->assertTrue($category->featureBlocks()->where('feature_block_id', $block->id)->exists());
    }

    public function test_can_attach_feature_block_to_product(): void
    {
        $product = Product::factory()->create();
        $block = ProductFeatureBlock::factory()->create();

        $product->featureBlocks()->attach($block->id, ['sort_order' => 1]);

        $this->assertTrue($product->featureBlocks()->where('feature_block_id', $block->id)->exists());
    }

    public function test_get_for_product_returns_product_blocks_when_override_exists(): void
    {
        $product = Product::factory()->create();
        $category = Category::factory()->create();
        $product->taxons()->attach($category->id);

        $productBlock = ProductFeatureBlock::factory()->create();
        $categoryBlock = ProductFeatureBlock::factory()->create();

        $product->featureBlocks()->attach($productBlock->id);
        $category->featureBlocks()->attach($categoryBlock->id);

        $result = ProductFeatureBlock::getForProduct($product);

        $this->assertCount(1, $result);
        $this->assertEquals($productBlock->id, $result->first()->id);
    }

    public function test_get_for_product_returns_category_blocks_when_no_override(): void
    {
        $product = Product::factory()->create();
        $category = Category::factory()->create();
        $product->taxons()->attach($category->id);

        $categoryBlock = ProductFeatureBlock::factory()->create();
        $category->featureBlocks()->attach($categoryBlock->id);

        $result = ProductFeatureBlock::getForProduct($product);

        $this->assertCount(1, $result);
        $this->assertEquals($categoryBlock->id, $result->first()->id);
    }

    public function test_get_for_product_returns_empty_when_no_blocks(): void
    {
        $product = Product::factory()->create();
        $category = Category::factory()->create();
        $product->taxons()->attach($category->id);

        $result = ProductFeatureBlock::getForProduct($product);

        $this->assertCount(0, $result);
    }

    public function test_active_scope_filters_only_active_blocks(): void
    {
        ProductFeatureBlock::factory()->create(['is_active' => true]);
        ProductFeatureBlock::factory()->create(['is_active' => false]);
        ProductFeatureBlock::factory()->create(['is_active' => true]);

        $activeBlocks = ProductFeatureBlock::active()->get();

        $this->assertCount(2, $activeBlocks);
        $this->assertTrue($activeBlocks->every(fn($block) => $block->is_active));
    }

    public function test_ordered_scope_sorts_by_sort_order(): void
    {
        $block3 = ProductFeatureBlock::factory()->create(['sort_order' => 3]);
        $block1 = ProductFeatureBlock::factory()->create(['sort_order' => 1]);
        $block2 = ProductFeatureBlock::factory()->create(['sort_order' => 2]);

        $ordered = ProductFeatureBlock::ordered()->get();

        $this->assertEquals($block1->id, $ordered[0]->id);
        $this->assertEquals($block2->id, $ordered[1]->id);
        $this->assertEquals($block3->id, $ordered[2]->id);
    }
}
