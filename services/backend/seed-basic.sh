#!/bin/bash

# Скрипт для выполнения минимального набора сидеров для базового функционала

echo "🌱 Запуск базовых сидеров..."

echo "📍 Регионы и доставка..."
php artisan db:seed --class=RussianRegionsSeeder
php artisan db:seed --class=DeliveryHandlingTypesSeeder
php artisan db:seed --class=CarriersSeeder
php artisan db:seed --class=ShippingLocationsSeeder
php artisan db:seed --class=ShippingMethodsSeeder

echo "💳 Методы оплаты..."
php artisan db:seed --class=PaymentMethodSeeder

echo "🛋️ Каталог товаров..."
php artisan db:seed --class=FurnitureCatalogSeeder

echo "👤 Роли и разрешения..."
php artisan db:seed --class=RolesAndPermissionsSeeder

echo ""
echo "ℹ️ Для генерации случайных сопутствующих товаров (до 4 на каждый основной товар) выполните:"
echo "   php artisan db:seed --class=ProductRelatedSeeder"

echo "✅ Базовые сидеры выполнены!"
echo ""
echo "Для полной инициализации выполните: php artisan db:seed"
