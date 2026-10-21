# Удаление старых интерфейсов доставки Laravel, 21.10.2026

Локальная ветка `feature/warehouse-service-tariffs`. На сервер не выкладывалось.

Удалены ресурсы Filament с их страницами, формами, таблицами и relation managers:
`ShippingLocations` и `RegionShippingMethods`. Это удаление редакторов и их маршрутов,
а не скрытие пунктов меню. База и старые заказы не удалялись; исходники восстанавливаются из Git.

Удалены API-контроллеры ShippingController, RegionController, CustomerLocalityController,
а также невостребованные после этого мосты LocalityGeoDirectory и CustomerDestinationResolver.

Сняты маршруты `/api/v1/regions{,/tree,/detect}`, `/api/v1/localities{,/regions,/detect,/{id}}`,
`/api/v1/shipping/locations{,/tree,/{locationId}}`, `/shipping/calculate`,
`/shipping/delivery-handling-types`, `/shipping/carriers`, `/shipping/shipping-methods`,
`/shipping/shipping-methods/calculate`, `/shipping/additional-services` (все с префиксом `/api/v1`).

Сохранены `/api/v1/address-directory/*`, `/api/v1/shipping/quote`, складские ресурсы,
справочники типов обработки/услуг/перевозчиков и исторические модели/данные.
Калькуляторы, на которые ещё ссылается старая ветка OrderController, в этом пакете
не удалены: это отдельная оставшаяся работа вместе с ценами и оплатой.

## Важно для фронта

Это несовместимое изменение. Старые запросы больше не обслуживаются. До выкладки
перевести актуальную ветку фронта на address-directory и складской quote.
В локальном checkout, аккаунте и RegionContext ещё есть обращения к старому контракту;
в этой работе фронт не менялся, так как переход ведёт Руслан. Нельзя объявлять весь
сайт работоспособным с прежним фронтом после удаления этих маршрутов.

Выбор города: `/address-directory/regions`, затем `/address-directory/localities`
с `parentExternalId` и `q`; подтверждение — `/address-directory/hierarchy/{externalId}`.
Услуги выбранного профиля — `service_options` ответа `/shipping/quote`.
Browser geolocation может передавать координаты в существующие механизмы каталога;
замены старого Laravel `/localities/detect` в этом пакете не добавлено.

После удаления файлов обновить Composer autoload и очистить кеши маршрутов/Filament.
Полный старый тестовый набор содержит ожидания удалённых контрактов и требует
миграции; целевые тесты проверяют отсутствие старых маршрутов и сохранность новых.
