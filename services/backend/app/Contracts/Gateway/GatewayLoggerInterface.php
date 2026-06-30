<?php

namespace App\Contracts\Gateway;

use Illuminate\Database\Eloquent\Model;

/**
 * Контракт логгера шлюзов.
 * Все интеграции (оплата, доставка и т.д.) пишут аудит через этот интерфейс.
 */
interface GatewayLoggerInterface
{
    /**
     * Записать событие шлюза.
     *
     * @param  string  $gateway  Идентификатор шлюза (raiffeisen_acquiring, …)
     * @param  string  $action   Действие (created, callback_success, …)
     * @param  string  $message  Человекочитаемое сообщение
     * @param  array<string, mixed>  $meta  Контекст: order_id, order_number, amount, ip, contact_email, …
     * @param  Model|null  $loggable  Связанная модель (Payment, Order, …)
     * @param  string  $channel  Канал: payment, delivery, …
     * @param  string  $level    info, warning, error
     */
    public function log(
        string $gateway,
        string $action,
        string $message,
        array $meta = [],
        ?Model $loggable = null,
        string $channel = 'payment',
        string $level = 'info'
    ): void;
}
