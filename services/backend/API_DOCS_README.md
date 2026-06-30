# API Документация

## Доступ к документации

Swagger UI доступен по адресу:
- **http://localhost:8000/api-docs/**

## Структура

Документация находится в `public/api-docs/`:
- `index.html` - страница Swagger UI
- `openapi.json` - OpenAPI спецификация со всеми эндпоинтами

## Обновление документации

Для добавления новых эндпоинтов отредактируйте файл `public/api-docs/openapi.json` вручную, добавив новые пути в секцию `paths`.

## Текущие эндпоинты

Документация включает:

### Auth (Аутентификация)
- `POST /api/v1/auth/register` - Регистрация
- `POST /api/v1/auth/login` - Вход
- `POST /api/v1/auth/logout` - Выход
- `GET /api/v1/auth/me` - Информация о пользователе
- `PUT /api/v1/auth/profile` - Обновление профиля
- `PUT /api/v1/auth/password` - Смена пароля
- `GET /api/v1/auth/notification-settings` - Получить настройки уведомлений
- `PUT /api/v1/auth/notification-settings` - Обновить настройки уведомлений

### Products (Товары)
- `GET /api/v1/products` - Список товаров (с фильтрацией, сортировкой, пагинацией)
- `GET /api/v1/products/{slug}` - Детальная информация о товаре
- `GET /api/v1/products/featured` - Рекомендуемые товары
- `GET /api/v1/products/new` - Новые товары
- `GET /api/v1/products/sale` - Товары со скидкой
- `GET /api/v1/products/search` - Поиск товаров
- `GET /api/v1/products/collections/{slug}` - Товары из подборки
- `GET /api/v1/products/{id}/related` - Похожие товары

### Cart (Корзина)
- `GET /api/v1/cart` - Получить содержимое корзины
- `POST /api/v1/cart` - Добавить товар в корзину
- `GET /api/v1/cart/count` - Получить количество товаров в корзине
- `PUT /api/v1/cart/{itemId}` - Обновить товар в корзине
- `DELETE /api/v1/cart/{itemId}` - Удалить товар из корзины
- `DELETE /api/v1/cart` - Очистить корзину

## Тестирование API

Swagger UI позволяет:
- Просматривать все эндпоинты
- Тестировать запросы прямо из браузера
- Видеть примеры запросов и ответов
- Авторизоваться через кнопку "Authorize"

## Метрики и тесты скорости API

- **Метрики в реальном времени:** после запросов к API агрегаты (count, avg_ms, max_ms) по маршрутам доступны по `GET /api-docs/metrics` (JSON). В Swagger UI есть ссылка «Метрики API».
- **Нагрузочные тесты (k6):** из корня репозитория выполните `npm run api:perf:generate` (генерация из openapi.json), затем `npm run api:perf:run` (запуск k6). Подробнее в `tools/k6/README.md`.
