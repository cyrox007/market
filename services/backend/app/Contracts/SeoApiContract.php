<?php

namespace App\Contracts;

/**
 * Контракт структуры SEO-данных для API и фронтенда.
 * Все сущности с SEO (товар, категория, страница и т.д.) отдают один и тот же формат.
 *
 * Формат массива:
 * - title: string|null
 * - description: string|null
 * - image: string|null
 * - canonical_url: string|null
 * - robots: string|null
 * - open_graph_title: string|null
 * - locale: string|null
 */
interface SeoApiContract
{
}
