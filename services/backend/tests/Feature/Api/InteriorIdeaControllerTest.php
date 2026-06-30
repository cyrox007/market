<?php

namespace Tests\Feature\Api;

use App\Models\Page\InteriorIdea;
use App\Models\Page\InteriorIdeaHotspot;
use App\Models\Product\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InteriorIdeaControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Cache::flush();
    }

    public function test_can_list_interior_ideas(): void
    {
        InteriorIdea::factory()->count(3)->create([
            'is_active' => true,
        ]);

        // Создаем неактивные идеи (не должны отображаться)
        InteriorIdea::factory()->count(2)->create([
            'is_active' => false,
        ]);

        $response = $this->getJson('/api/v1/interior-ideas');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'image',
                        'image_thumb',
                        'image_main',
                        'hotspots',
                    ],
                ],
            ])
            ->assertJsonCount(3, 'data'); // Только активные идеи
    }

    public function test_interior_ideas_are_sorted_by_priority(): void
    {
        $idea1 = InteriorIdea::factory()->create([
            'is_active' => true,
            'priority' => 10,
        ]);

        $idea2 = InteriorIdea::factory()->create([
            'is_active' => true,
            'priority' => 5,
        ]);

        $idea3 = InteriorIdea::factory()->create([
            'is_active' => true,
            'priority' => 1,
        ]);

        $response = $this->getJson('/api/v1/interior-ideas');

        $response->assertStatus(200);
        $data = $response->json('data');
        
        // Проверяем, что идеи отсортированы по приоритету (asc)
        $this->assertEquals($idea3->id, $data[0]['id']);
        $this->assertEquals($idea2->id, $data[1]['id']);
        $this->assertEquals($idea1->id, $data[2]['id']);
    }

    public function test_interior_ideas_include_hotspots_with_products(): void
    {
        $product = Product::factory()->create([
            'state' => 'active',
            'parent_product_id' => null,
        ]);

        $idea = InteriorIdea::factory()->create([
            'is_active' => true,
        ]);

        $hotspot1 = InteriorIdeaHotspot::factory()->create([
            'interior_idea_id' => $idea->id,
            'product_id' => $product->id,
            'x' => 30.5,
            'y' => 50.2,
        ]);

        $hotspot2 = InteriorIdeaHotspot::factory()->create([
            'interior_idea_id' => $idea->id,
            'product_id' => $product->id,
            'x' => 70.0,
            'y' => 40.0,
        ]);

        $response = $this->getJson('/api/v1/interior-ideas');

        $response->assertStatus(200);
        $data = $response->json('data');
        $ideaData = collect($data)->firstWhere('id', $idea->id);

        $this->assertNotNull($ideaData);
        $this->assertArrayHasKey('hotspots', $ideaData);
        $this->assertCount(2, $ideaData['hotspots']);

        // Проверяем структуру хотспота
        $hotspotData = collect($ideaData['hotspots'])->firstWhere('id', $hotspot1->id);
        $this->assertNotNull($hotspotData);
        $this->assertArrayHasKey('id', $hotspotData);
        $this->assertArrayHasKey('x', $hotspotData);
        $this->assertArrayHasKey('y', $hotspotData);
        $this->assertArrayHasKey('product', $hotspotData);
        $this->assertEquals(30.5, $hotspotData['x']);
        $this->assertEquals(50.2, $hotspotData['y']);

        // Проверяем структуру товара
        $productData = $hotspotData['product'];
        $this->assertNotNull($productData);
        $this->assertArrayHasKey('id', $productData);
        $this->assertArrayHasKey('name', $productData);
        $this->assertArrayHasKey('price', $productData);
        $this->assertArrayHasKey('slug', $productData);
        $this->assertArrayHasKey('full_path', $productData);
        $this->assertEquals($product->id, $productData['id']);
    }

    public function test_hotspots_are_sorted_by_priority(): void
    {
        $product = Product::factory()->create([
            'state' => 'active',
            'parent_product_id' => null,
        ]);

        $idea = InteriorIdea::factory()->create([
            'is_active' => true,
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

        $response = $this->getJson('/api/v1/interior-ideas');

        $response->assertStatus(200);
        $data = $response->json('data');
        $ideaData = collect($data)->firstWhere('id', $idea->id);
        $hotspots = $ideaData['hotspots'];

        // Проверяем, что хотспоты отсортированы по приоритету (asc)
        $this->assertEquals($hotspot3->id, $hotspots[0]['id']);
        $this->assertEquals($hotspot2->id, $hotspots[1]['id']);
        $this->assertEquals($hotspot1->id, $hotspots[2]['id']);
    }

    public function test_interior_ideas_without_hotspots_return_empty_array(): void
    {
        $idea = InteriorIdea::factory()->create([
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/interior-ideas');

        $response->assertStatus(200);
        $data = $response->json('data');
        $ideaData = collect($data)->firstWhere('id', $idea->id);

        $this->assertNotNull($ideaData);
        $this->assertArrayHasKey('hotspots', $ideaData);
        $this->assertIsArray($ideaData['hotspots']);
        $this->assertEmpty($ideaData['hotspots']);
    }

    public function test_interior_ideas_with_images_return_image_urls(): void
    {
        $idea = InteriorIdea::factory()->create([
            'is_active' => true,
        ]);

        // Добавляем изображение через Spatie Media Library
        $file = UploadedFile::fake()->image('interior.jpg', 820, 880);
        $idea->addMediaFromFile($file->getRealPath())
            ->toMediaCollection('image');

        $response = $this->getJson('/api/v1/interior-ideas');

        $response->assertStatus(200);
        $data = $response->json('data');
        $ideaData = collect($data)->firstWhere('id', $idea->id);

        $this->assertNotNull($ideaData);
        $this->assertArrayHasKey('image', $ideaData);
        $this->assertArrayHasKey('image_thumb', $ideaData);
        $this->assertArrayHasKey('image_main', $ideaData);
        $this->assertNotNull($ideaData['image']);
    }

    public function test_interior_ideas_without_images_return_null_urls(): void
    {
        $idea = InteriorIdea::factory()->create([
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/interior-ideas');

        $response->assertStatus(200);
        $data = $response->json('data');
        $ideaData = collect($data)->firstWhere('id', $idea->id);

        $this->assertNotNull($ideaData);
        $this->assertArrayHasKey('image', $ideaData);
        $this->assertSame('', $ideaData['image']);
    }

    public function test_only_active_interior_ideas_are_returned(): void
    {
        InteriorIdea::factory()->count(3)->create([
            'is_active' => true,
        ]);

        InteriorIdea::factory()->count(2)->create([
            'is_active' => false,
        ]);

        $response = $this->getJson('/api/v1/interior-ideas');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }
}
