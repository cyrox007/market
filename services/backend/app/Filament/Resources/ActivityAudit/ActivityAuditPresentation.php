<?php

namespace App\Filament\Resources\ActivityAudit;

use App\Models\ActivityAuditEvent;
use App\Models\Order\OrderStatus;

final class ActivityAuditPresentation
{
    public const RESULTS = ['success' => 'Успешно', 'denied' => 'Отказ', 'error' => 'Ошибка'];
    public const SOURCES = ['admin' => 'Админка', 'storefront' => 'Витрина', 'integration' => 'Интеграция', 'bank' => 'Банк', 'system' => 'Система'];
    private const ACTORS = ['admin' => 'Администратор', 'user' => 'Покупатель', 'guest' => 'Гость', 'integration' => 'Интеграция', 'bank' => 'Банк', 'system' => 'Система'];
    private const SUBJECTS = ['product' => 'Товар', 'user' => 'Пользователь', 'order' => 'Заказ', 'role' => 'Роль', 'address' => 'Адрес', 'category' => 'Категория', 'cart_item' => 'Позиция корзины', 'permission' => 'Разрешение'];
    private const FIELDS = ['name' => 'Название', 'price' => 'Цена', 'sale_price' => 'Цена со скидкой', 'total' => 'Сумма', 'status' => 'Статус', 'comment' => 'Комментарий', 'is_active' => 'Активность', 'is_enabled' => 'Включено', 'is_coming_soon' => 'Скоро', 'allow_preorder' => 'Предзаказ', 'amount_paid' => 'Оплачено', 'roles' => 'Роли', 'password' => 'Пароль', 'email' => 'Почта', 'phone' => 'Телефон', 'contact_email' => 'Почта для связи', 'contact_phone' => 'Телефон для связи', 'contact_name' => 'Имя для связи', 'address_id' => 'Адрес', 'payment_method' => 'Способ оплаты', 'delivery_type' => 'Способ доставки', 'configuration' => 'Комплектация'];

    public static function action(ActivityAuditEvent $event): string
    {
        $labels = [
            'audit.view' => 'Открыли журнал', 'audit.export' => 'Выгрузили CSV',
            'audit.user_timeline_view' => 'Открыли историю пользователя', 'audit.settings_changed' => 'Изменили настройки аудита',
            'admin.login' => 'Вход в админку', 'auth.login' => 'Вход в аккаунт', 'auth.logout' => 'Выход из аккаунта',
            'auth.session_ended' => 'Сессия завершена', 'auth.register' => 'Регистрация',
            'product.updated' => 'Изменили товар', 'product.created' => 'Создали товар', 'product.deleted' => 'Удалили товар',
            'order.updated' => 'Изменили заказ', 'order.created' => 'Создали заказ', 'order.deleted' => 'Удалили заказ',
            'checkout.submit' => 'Оформили заказ', 'checkout.one_click' => 'Заказ в один клик',
            'order.view' => 'Открыли заказ', 'order.cancel' => 'Отмена заказа',
            'order.cancellation_request' => 'Запросили отмену заказа',
            'payment.updated' => 'Обновили платёж', 'refund.updated' => 'Обновили возврат',
            'role.permissions_saved' => 'Сохранили права роли', 'user.roles_changed' => 'Изменили роли пользователя',
            'user.roles_assigned' => 'Назначили роли пользователю', 'user.updated' => 'Изменили пользователя',
            'profile.updated' => 'Изменили профиль', 'address.created' => 'Добавили адрес',
            'address.updated' => 'Изменили адрес', 'address.deleted' => 'Удалили адрес',
            'catalog.product_view' => 'Просмотрели товар', 'catalog.search' => 'Поиск по каталогу', 'catalog.list' => 'Открыли каталог',
        ];

        return $labels[$event->event_type] ?? $event->event_type;
    }

    public static function actor(ActivityAuditEvent $event): string
    {
        $type = self::ACTORS[$event->actor_type] ?? $event->actor_type;
        if ($event->actor_id === null) {
            return $type;
        }

        return ($event->actor?->name ?: $type).' #'.$event->actor_id;
    }

    public static function subject(ActivityAuditEvent $event): ?string
    {
        if ($event->subject_type === null) {
            return null;
        }

        $type = self::SUBJECTS[$event->subject_type] ?? $event->subject_type;
        $name = match ($event->subject_type) {
            'product' => $event->details['changes']['name'][1] ?? $event->product?->name,
            'order' => $event->order?->number,
            default => null,
        };

        return $type.($event->subject_id ? ' #'.$event->subject_id : '').($name ? ' · '.$name : '');
    }

    public static function summary(ActivityAuditEvent $event): ?string
    {
        $fields = $event->details['fields'] ?? [];
        if ($fields) {
            return 'Изменено: '.implode(', ', array_map(fn ($field) => self::FIELDS[$field] ?? $field, $fields));
        }
        if (isset($event->details['behavior_enabled'])) {
            return 'События каталога: '.($event->details['behavior_enabled'] ? 'включены' : 'выключены');
        }

        return null;
    }

    public static function details(ActivityAuditEvent $event): array
    {
        $details = $event->details ?? [];
        $lines = [];
        foreach ($details['changes'] ?? [] as $field => [$before, $after]) {
            $lines[self::FIELDS[$field] ?? $field] = self::value($before).' → '.self::value($after);
        }
        foreach (['status' => 'Статус заказа', 'payment_status' => 'Статус платежа', 'refund_status' => 'Статус возврата'] as $field => $label) {
            if (isset($details['new_'.$field])) {
                $status = fn (string $value) => OrderStatus::tryFrom($value)?->label() ?? $value;
                $lines[$label] = isset($details['previous_'.$field])
                    ? $status($details['previous_'.$field]).' → '.$status($details['new_'.$field])
                    : $status($details['new_'.$field]);
            }
        }
        if (isset($details['behavior_enabled'])) {
            $lines['События каталога'] = $details['behavior_enabled'] ? 'Включены' : 'Выключены';
        }
        $categories = ['security' => 'Безопасность', 'admin' => 'Администрирование', 'order' => 'Заказы', 'account' => 'Аккаунт', 'shopping' => 'Корзина и оформление', 'behavior' => 'Каталог'];
        foreach ($details['retention_days'] ?? [] as $category => $days) {
            $lines['Хранение · '.($categories[$category] ?? $category)] = $days.' дн.';
        }
        foreach (['http_status' => 'Код ответа', 'changed_count' => 'Количество', 'product_id' => 'ID товара', 'quantity' => 'Количество товара', 'order_id' => 'ID заказа', 'payment_id' => 'ID платежа', 'refund_id' => 'ID возврата', 'added_count' => 'Добавлено', 'removed_count' => 'Удалено', 'selected_count' => 'Выбрано', 'product_count' => 'Товаров', 'category_count' => 'Категорий', 'product_ids' => 'ID товаров', 'target_ids' => 'ID целей'] as $key => $label) {
            if (isset($details[$key])) {
                $lines[$label] = is_array($details[$key]) ? implode(', ', $details[$key]) : self::value($details[$key]);
            }
        }

        return $lines;
    }

    private static function value(mixed $value): string
    {
        return match (true) {
            $value === null || $value === '' => '—',
            is_bool($value) => $value ? 'Да' : 'Нет',
            default => (string) $value,
        };
    }
}
