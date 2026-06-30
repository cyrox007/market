<?php

namespace Tests\Unit;

use App\Models\Page\InteriorIdea;
use App\Models\Page\InteriorIdeaHotspot;
use App\Models\Product\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InteriorIdeaHotspotModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_hotspot_belongs_to_interior_idea(): void
    {
        $idea = InteriorIdea::factory()->create();
        $product = Product::factory()->create([
            'state' => 'active',
            'parent_product_id' => null,
        ]);

        $hotspot = InteriorIdeaHotspot::factory()->create([
            'interior_idea_id' => $idea->id,
            'product_id' => $product->id,
        ]);

        $this->assertEquals($idea->id, $hotspot->interiorIdea->id);
        $this->assertInstanceOf(InteriorIdea::class, $hotspot->interiorIdea);
    }

    public function test_hotspot_belongs_to_product(): void
    {
        $idea = InteriorIdea::factory()->create();
        $product = Product::factory()->create([
            'state' => 'active',
            'parent_product_id' => null,
        ]);

        $hotspot = InteriorIdeaHotspot::factory()->create([
            'interior_idea_id' => $idea->id,
            'product_id' => $product->id,
        ]);

        $this->assertEquals($product->id, $hotspot->product->id);
        $this->assertInstanceOf(Product::class, $hotspot->product);
    }

    public function test_hotspot_coordinates_are_stored_as_decimals(): void
    {
        $idea = InteriorIdea::factory()->create();
        $product = Product::factory()->create([
            'state' => 'active',
            'parent_product_id' => null,
        ]);

        $hotspot = InteriorIdeaHotspot::factory()->create([
            'interior_idea_id' => $idea->id,
            'product_id' => $product->id,
            'x' => 30.55,
            'y' => 50.77,
        ]);

        $this->assertEquals(30.55, (float) $hotspot->x);
        $this->assertEquals(50.77, (float) $hotspot->y);
    }

    public function test_hotspot_coordinates_are_within_valid_range(): void
    {
        $idea = InteriorIdea::factory()->create();
        $product = Product::factory()->create([
            'state' => 'active',
            'parent_product_id' => null,
        ]);

        // Тест с валидными координатами
        $hotspot = InteriorIdeaHotspot::factory()->create([
            'interior_idea_id' => $idea->id,
            'product_id' => $product->id,
            'x' => 50.0,
            'y' => 50.0,
        ]);

        $this->assertGreaterThanOrEqual(0, (float) $hotspot->x);
        $this->assertLessThanOrEqual(100, (float) $hotspot->x);
        $this->assertGreaterThanOrEqual(0, (float) $hotspot->y);
        $this->assertLessThanOrEqual(100, (float) $hotspot->y);
    }

    public function test_hotspot_cascade_deletes_with_interior_idea(): void
    {
        $idea = InteriorIdea::factory()->create();
        $product = Product::factory()->create([
            'state' => 'active',
            'parent_product_id' => null,
        ]);

        $hotspot = InteriorIdeaHotspot::factory()->create([
            'interior_idea_id' => $idea->id,
            'product_id' => $product->id,
        ]);

        $hotspotId = $hotspot->id;

        $idea->delete();

        $this->assertDatabaseMissing('interior_idea_hotspots', [
            'id' => $hotspotId,
        ]);
    }

    public function test_hotspot_cascade_deletes_with_product(): void
    {
        $idea = InteriorIdea::factory()->create();
        $product = Product::factory()->create([
            'state' => 'active',
            'parent_product_id' => null,
        ]);

        $hotspot = InteriorIdeaHotspot::factory()->create([
            'interior_idea_id' => $idea->id,
            'product_id' => $product->id,
        ]);

        $hotspotId = $hotspot->id;

        $product->delete();

        $this->assertDatabaseMissing('interior_idea_hotspots', [
            'id' => $hotspotId,
        ]);
    }
}
