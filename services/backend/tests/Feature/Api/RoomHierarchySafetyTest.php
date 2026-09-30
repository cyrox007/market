<?php

namespace Tests\Feature\Api;

use App\Models\Product\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RoomHierarchySafetyTest extends TestCase
{
    use RefreshDatabase;

    private function makeRoom(string $name, string $slug, ?int $parentId = null): Room
    {
        $room = new Room();
        $room->name = $name;
        $room->slug = $slug;
        $room->parent_id = $parentId;
        $room->is_active = true;
        $room->save();

        return $room;
    }

    public function test_room_cannot_be_its_own_parent(): void
    {
        $room = $this->makeRoom('Гостиная', 'gostinaya');
        $room->parent_id = $room->id;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Комната не может быть родителем самой себе.');

        $room->save();
    }

    public function test_room_cannot_be_moved_under_its_descendant(): void
    {
        $parent = $this->makeRoom('Гостиная', 'gostinaya');
        $child = $this->makeRoom('Диваны', 'divany', $parent->id);

        $parent->parent_id = $child->id;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Нельзя переместить комнату внутрь собственного поддерева.');

        $parent->save();
    }

    public function test_room_persists_generated_slug_when_slug_is_missing(): void
    {
        $name = 'Детская комната';
        $expectedSlug = Str::slug($name);

        $room = new Room();
        $room->name = $name;
        $room->slug = null;
        $room->is_active = true;
        $room->save();

        $this->assertSame($expectedSlug, $room->getRawOriginal('slug'));
        $this->assertDatabaseHas('taxons', [
            'id' => $room->id,
            'slug' => $expectedSlug,
        ]);
    }
}
