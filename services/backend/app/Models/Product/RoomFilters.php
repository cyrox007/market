<?php

namespace App\Models\Product;

/**
 * Доменная логика фильтров комнаты: сужение ограничений вниз по дереву.
 * Наследник только сужает: цена — строже, цвета/характеристики — пересечение.
 * Чистый класс без зависимостей от БД — тестируется юнитами.
 */
final class RoomFilters
{
    /**
     * Сужает $base ограничениями $add (родитель → потомок / комната → запрос пользователя).
     */
    public static function narrow(array $base, array $add): array
    {
        if (isset($add['price_min']) && $add['price_min'] !== null && $add['price_min'] !== '') {
            $base['price_min'] = isset($base['price_min'])
                ? max((float) $base['price_min'], (float) $add['price_min'])
                : $add['price_min'];
        }
        if (isset($add['price_max']) && $add['price_max'] !== null && $add['price_max'] !== '') {
            $base['price_max'] = isset($base['price_max'])
                ? min((float) $base['price_max'], (float) $add['price_max'])
                : $add['price_max'];
        }
        if (! empty($add['colors'])) {
            $base['colors'] = isset($base['colors'])
                ? array_values(array_intersect($base['colors'], (array) $add['colors']))
                : array_values((array) $add['colors']);
        }
        if (! empty($add['attributes'])) {
            $base['attributes'] = $base['attributes'] ?? [];
            foreach ($add['attributes'] as $slug => $values) {
                $base['attributes'][$slug] = isset($base['attributes'][$slug])
                    ? array_values(array_intersect($base['attributes'][$slug], (array) $values))
                    : array_values((array) $values);
            }
        }

        return $base;
    }

    /**
     * Итоговые ограничения для цепочки комнат, заданной от корня к листу.
     * Каждый следующий уровень сужает предыдущий.
     *
     * @param  array<int, array>  $filtersRootToLeaf  фильтры комнат по порядку root → leaf
     */
    public static function resolveChain(array $filtersRootToLeaf): array
    {
        $merged = [];
        foreach ($filtersRootToLeaf as $filters) {
            $merged = self::narrow($merged, $filters ?? []);
        }

        return $merged;
    }
}
