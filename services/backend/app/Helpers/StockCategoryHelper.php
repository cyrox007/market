<?php

namespace App\Helpers;

use App\Models\Settings\ProductStockSettings;

class StockCategoryHelper
{
    /**
     * Форматирует остаток товара в категорию
     *
     * @param int $stock Количество товара на складе
     * @return string Категория остатка: "мало", "средне", "много" или точное число
     */
    public static function formatStock(int $stock): string
    {
        $settings = ProductStockSettings::getInstance();

        if ($stock < $settings->stock_low_max) {
            return 'мало';
        }

        if ($stock <= $settings->stock_medium_max) {
            return 'средне';
        }

        if ($stock <= $settings->stock_high_max) {
            return 'много';
        }

        // Если show_exact_above > 0 и stock > show_exact_above, показываем точное число
        if ($settings->show_exact_above > 0 && $stock > $settings->show_exact_above) {
            return (string) $stock;
        }

        return 'много';
    }

    /**
     * Текст для бейджа на сайте: категория или «N шт.» по настройкам из админки.
     */
    public static function formatStockDisplay(int $stock): string
    {
        $settings = ProductStockSettings::getInstance();
        $category = self::formatStock($stock);

        if ($stock > 0 && $settings->show_exact_above > 0 && $stock > $settings->show_exact_above) {
            return $stock . ' шт.';
        }

        return $category;
    }
}
