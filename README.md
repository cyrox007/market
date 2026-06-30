Ветка подбора стека

## Быстрый старт

1. **Поднять окружение**
   ```bash
   docker compose up -d
   ```

2. **Миграции** (из корня проекта)
   ```bash
   docker compose exec app php artisan migrate
   ```

3. **Базовые сидеры** (регионы, доставка, оплата, каталог, роли)
   ```bash
   docker compose exec app bash -c "cd /var/www && ./seed-basic.sh"
   ```

4. **Создать суперадмина**
   ```bash
   # Существующий пользователь — только назначить роль:
   docker compose exec app php /var/www/assign_super_admin.php admin@example.com

   # Новый пользователь — создать и назначить роль:
   docker compose exec app php /var/www/assign_super_admin.php admin@example.com "Super Admin" your_password
   ```

5. **Админка:** http://localhost:8000/admin_sv/login

## Региональность и кэш (актуальные правила)

- Для API каталога и корзины приоритетный параметр локации: `shipping_location_id`, `region_id` используется как legacy fallback.
- Региональная видимость товаров применяется server-side в `GET /api/v1/products` и `GET /api/v1/products/search`, чтобы `total` и пагинация совпадали с фактическим списком.
- При изменении `ProductRegionRule` выполняется инвалидация каталожных cache-tags (`product_index_cache`, `product_search_cache`).
- На фронтенде SWR-ключи для регионозависимых блоков (`featured/new/sale`, list/search/cart) должны всегда включать регион.
