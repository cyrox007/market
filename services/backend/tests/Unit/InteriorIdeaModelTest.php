<?php

namespace Tests\Unit;

use App\Models\Page\InteriorIdea;
use App\Models\Page\InteriorIdeaHotspot;
use App\Models\Product\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InteriorIdeaModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_interior_idea_has_hotspots_relationship(): void
    {
        $idea = InteriorIdea::factory()->create();
        $product = Product::factory()->create([
            'state' => 'active',
            'parent_product_id' => null,
        ]);

        $hotspot1 = InteriorIdeaHotspot::factory()->create([
            'interior_idea_id' => $idea->id,
            'product_id' => $product->id,
        ]);

        $hotspot2 = InteriorIdeaHotspot::factory()->create([
            'interior_idea_id' => $idea->id,
            'product_id' => $product->id,
        ]);

        $this->assertCount(2, $idea->hotspots);
        $this->assertTrue($idea->hotspots->contains($hotspot1));
        $this->assertTrue($idea->hotspots->contains($hotspot2));
    }

    public function test_hotspots_are_ordered_by_priority(): void
    {
        $idea = InteriorIdea::factory()->create();
        $product = Product::factory()->create([
            'state' => 'active',
            'parent_product_id' => null,
        ]);

        $hotspot1 = InteriorIdeaHotspot::factory()->create([
            'interior_idea_id' => $idea->id,
            'product_id' => $product->id,
            'priority' => 10,
        ]);

        $hotspot2 = InteriorIdeaHotspot::factory()->create([
            'interior_idea_id' => $idea->id,
            'product_id' => $product->id,
            'priority' => 5,
        ]);

        $hotspot3 = InteriorIdeaHotspot::factory()->create([
            'interior_idea_id' => $idea->id,
            'product_id' => $product->id,
            'priority' => 1,
        ]);

        $hotspots = $idea->hotspots;

        $this->assertEquals($hotspot3->id, $hotspots[0]->id);
        $this->assertEquals($hotspot2->id, $hotspots[1]->id);
        $this->assertEquals($hotspot1->id, $hotspots[2]->id);
    }

    public function test_interior_idea_can_have_media(): void
    {
        $idea = InteriorIdea::factory()->create();

        $this->assertTrue(method_exists($idea, 'getMedia'));
        $this->assertTrue(method_exists($idea, 'addMediaFromFile'));
    }

    public function test_interior_idea_has_active_scope(): void
    {
        InteriorIdea::factory()->create(['is_active' => true]);
        InteriorIdea::factory()->create(['is_active' => false]);

        $activeIdeas = InteriorIdea::active()->get();

        $this->assertCount(1, $activeIdeas);
        $this->assertTrue($activeIdeas->first()->is_active);
    }

    public function test_interior_idea_has_ordered_scope(): void
    {
        $idea1 = InteriorIdea::factory()->create(['priority' => 10]);
        $idea2 = InteriorIdea::factory()->create(['priority' => 5]);
        $idea3 = InteriorIdea::factory()->create(['priority' => 1]);

        $orderedIdeas = InteriorIdea::ordered()->get();

        $this->assertEquals($idea3->id, $orderedIdeas[0]->id);
        $this->assertEquals($idea2->id, $orderedIdeas[1]->id);
        $this->assertEquals($idea1->id, $orderedIdeas[2]->id);
    }
}
