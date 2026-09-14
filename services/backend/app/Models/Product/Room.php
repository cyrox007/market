<?php

namespace App\Models\Product;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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
        // Запрет создавать комнату глубже MAX_DEPTH уровней.
        static::saving(function (Room $room) {
            if ($room->parent_id) {
                $parent = static::find($room->parent_id);
                if ($parent && $parent->depth() >= self::MAX_DEPTH) {
                    throw new \RuntimeException('Максимальная вложенность комнат — ' . self::MAX_DEPTH . ' уровня.');
                }
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
        $node = $this;
        while ($node) {
            $chain[] = $node;
            $node = $node->parent;
        }

        return $chain;
    }
}
