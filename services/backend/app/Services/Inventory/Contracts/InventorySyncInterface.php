<?php

namespace App\Services\Inventory\Contracts;

use App\Models\Order\Order;

/**
 * Интерфейс для синхронизации заказов и остатков с внешними системами (1С, другие ERP)
 */
interface InventorySyncInterface
{
    /**
     * Синхронизировать созданный заказ с внешней системой
     *
     * @param Order $order
     * @return bool Успешность синхронизации
     */
    public function syncOrderCreated(Order $order): bool;

    /**
     * Синхронизировать отмененный заказ с внешней системой
     *
     * @param Order $order
     * @return bool
     */
    public function syncOrderCancelled(Order $order): bool;

    /**
     * Синхронизировать завершенный заказ с внешней системой
     *
     * @param Order $order
     * @return bool
     */
    public function syncOrderCompleted(Order $order): bool;

    /**
     * Получить остатки товаров из внешней системы
     *
     * @param array $productIds Массив ID товаров
     * @return array Массив ['product_id' => stock]
     */
    public function fetchStockLevels(array $productIds): array;

    /**
     * Получить информацию о товаре из внешней системы
     *
     * @param string $sku Артикул товара
     * @return array|null Данные товара или null если не найден
     */
    public function fetchProductBySku(string $sku): ?array;
}
