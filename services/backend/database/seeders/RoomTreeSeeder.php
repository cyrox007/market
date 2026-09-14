<?php

namespace Database\Seeders;

use App\Models\Product\Room;
use Illuminate\Database\Seeder;

/**
 * Базовый наполнитель дерева комнат. Наследники задают только данные через tree().
 * Идемпотентно по slug (firstOrNew), ничего не удаляет.
 */
abstract class RoomTreeSeeder extends Seeder
{
    /**
     * Дерево комнат: массив узлов вида
     * ['name' => ..., 'slug' => ..., 'filters' => [...]?, 'cats' => [...]?, 'children' => [...]?].
     */
    abstract protected function tree(): array;

    public function run(): void
    {
        foreach ($this->tree() as $node) {
            $this->makeNode($node, null, 0);
        }
    }

    protected function makeNode(array $data, ?int $parentId, int $priority): void
    {
        $room = Room::firstOrNew(['slug' => $data['slug']]);
        $room->name = $data['name'];
        $room->parent_id = $parentId;
        $room->priority = $priority;
        $room->is_active = true;
        $room->filters = $data['filters'] ?? null;
        $room->save();

        if (! empty($data['cats'])) {
            $room->productCategories()->sync($data['cats']);
        }

        $childPriority = 0;
        foreach ($data['children'] ?? [] as $child) {
            $this->makeNode($child, $room->id, $childPriority++);
        }
    }
}
