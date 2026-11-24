<?php

namespace Tests\Feature\Api;

use App\Models\Page\InteriorIdea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Retirement regression test: the public API is gone, archived records are kept.
 * The existing schema is intentionally retained for safe data migration/backup.
 */
class InteriorIdeaControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_retired_interior_ideas_api_is_unavailable_without_deleting_archived_records(): void
    {
        $idea = InteriorIdea::factory()->create([
            'is_active' => true,
        ]);

        $this->getJson('/api/v1/interior-ideas')->assertNotFound();

        $this->assertDatabaseHas('interior_ideas', [
            'id' => $idea->id,
            'is_active' => true,
        ]);
    }
}
