<?php

namespace App\Contracts;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use App\Models\Product\Product;
use App\Models\Product\Category;

/**
 * Интерфейс для блоков товаров
 * 
 * Этот интерфейс определяет общий контракт для всех типов блоков товаров
 * (фичи, доставка и будущие типы блоков).
 * 
 * Для добавления нового типа блока:
 * 1. Создайте модель, реализующую этот интерфейс
 * 2. Добавьте миграции для таблицы блока и pivot таблиц
 * 3. Добавьте связи в модели Category и Product
 * 4. Создайте Filament Resource и RelationManagers
 * 5. Реализуйте метод getForProduct с логикой переопределения
 */
interface ProductBlockInterface
{
    /**
     * Получить связи с категориями
     */
    public function categories(): BelongsToMany;

    /**
     * Получить связи с товарами (для переопределения)
     */
    public function products(): BelongsToMany;

    /**
     * Получить блоки для товара с учетом переопределения
     * 
     * Логика:
     * 1. Сначала проверяем, есть ли блоки, привязанные к товару
     * 2. Если нет, берем блоки из категории товара
     * 3. Если и категории нет, возвращаем пустую коллекцию
     * 
     * @param Product $product
     * @return Collection
     */
    public static function getForProduct(Product $product): Collection;

    /**
     * Получить блоки для категории
     * 
     * @param Category $category
     * @return Collection
     */
    public static function getForCategory(Category $category): Collection;
}
