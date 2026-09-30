<?php

namespace App\Models\Product;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

/**
 * Комната — узел второй Vanilo-таксономии «rooms».
 * Лист комнаты не хранит товары, а сводится к продуктовым категориям (+ фильтр).
 */
class Room extends Category
{
    public const ROOT_PATH = '/rooms';

    /** Максимально допустимая вложенность комнат. */
    public const MAX_DEPTH = 4;

    protected static function booted(): void
    {
        static::saving(function (Room $room) {
            // URL комнаты должен быть физически сохранён в БД: accessor Category::slug
            // умеет вычислять slug на лету, но API ищет комнату по колонке taxons.slug.
            $rawSlug = $room->getAttributes()['slug'] ?? null;
            if (blank($rawSlug) && filled($room->name)) {
                $room->slug = Str::slug($room->name);
            }

            if (! $room->parent_id) {
                return;
            }

            $parentId = (int) $room->parent_id;
            $roomId = $room->exists ? (int) $room->getKey() : null;

            if ($roomId !== null && $parentId === $roomId) {
                throw new \RuntimeException('Комната не может быть родителем самой себе.');
            }

            $parent = static::find($parentId);
            if (! $parent) {
                return;
            }

            // Проверяем будущую цепочку родителей ДО сохранения. Это одновременно:
            // 1) не даёт переместить комнату под собственного потомка;
            // 2) не даёт сохранить уже циклическую цепочку;
            // 3) контролирует максимальную глубину без рекурсивного depth() на битом дереве.
            $visited = [];
            $prospectiveDepth = 1; // сама сохраняемая комната
            $node = $parent;

            while ($node) {
                $nodeId = (int) $node->getKey();

                if ($roomId !== null && $nodeId === $roomId) {
                    throw new \RuntimeException('Нельзя переместить комнату внутрь собственного поддерева.');
                }

                if (isset($visited[$nodeId])) {
                    throw new \RuntimeException('В дереве комнат обнаружена циклическая связь.');
                }
                $visited[$nodeId] = true;

                $prospectiveDepth++;
                if ($prospectiveDepth > self::MAX_DEPTH) {
                    throw new \RuntimeException('Максимальная вложенность комнат — ' . self::MAX_DEPTH . ' уровня.');
                }

                $node = $node->parent;
            }
        });
    }

    /**
     * Уровень комнаты в дереве (корень = 1).
     */
    public function depth(): int
    {
        return count($this->ancestorsChain());
    }

    /**
     * Корневая (самая верхняя) комната дерева.
     */
    public function rootAncestor(): Room
    {
        $chain = $this->ancestorsChain();

        return end($chain);
    }

    protected static function taxonomySlug(): string
    {
        return 'rooms';
    }

    protected static function taxonomyName(): string
    {
        return 'Комнаты';
    }

    protected function casts(): array
    {
        return array_merge(parent::casts(), ['filters' => 'array']);
    }

    /**
     * Продуктовые категории, к которым сводится лист комнаты.
     */
    public function productCategories(): BelongsToMany
    {
        return $this->belongsToMany(
            Category::class,
            'room_taxon_category',
            'room_taxon_id',
            'category_id'
        )->withTimestamps();
    }

    /**
     * Итоговые фильтры комнаты с учётом наследования по цепочке предков.
     * Наследник только сужает ограничения родителя (цена — строже, цвета/характеристики — пересечение).
     */
    public function effectiveFilters(): array
    {
        // От корня к текущей комнате — RoomFilters сужает каждый уровень.
        $chainRootToLeaf = array_map(
            fn (Room $room) => $room->filters ?? [],
            array_reverse($this->ancestorsChain())
        );

        return RoomFilters::resolveChain($chainRootToLeaf);
    }

    /**
     * Цепочка предков от текущей комнаты к корню: [self, parent, …, root].
     */
    public function ancestorsChain(): array
    {
        $chain = [];
        $visited = [];
        $node = $this;

        while ($node) {
            $nodeId = (int) $node->getKey();
            if ($nodeId && isset($visited[$nodeId])) {
                throw new \RuntimeException('В дереве комнат обнаружена циклическая связь.');
            }
            if ($nodeId) {
                $visited[$nodeId] = true;
            }

            $chain[] = $node;
            $node = $node->parent;
        }

        return $chain;
    }
}
