<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/** Configuration audit only: no payments, refunds, orders or external requests. */
class CheckPilotReadiness extends Command
{
    protected $signature = 'pilot:readiness {--warehouse=1 : Central pilot warehouse ID}';
    protected $description = 'Read-only pilot configuration audit; never prints gateway secrets';

    public function handle(): int
    {
        $rows = [];
        $blocked = false;
        $check = function (string $name, bool $ok, string $failure) use (&$rows, &$blocked): void {
            $rows[] = [$name, $ok ? 'OK' : 'BLOCK', $ok ? 'Настроено' : $failure];
            $blocked = $blocked || ! $ok;
        };
        $warehouse = \App\Models\Inventory\Warehouse::with(['physicalSite', 'deliveryProfiles'])->find((int) $this->option('warehouse'));
        $check('Центральный склад', (bool) ($warehouse?->is_active && $warehouse->source_type === 'physical' && $warehouse->stock_mode === 'quantity'), 'Выберите активный физический склад с количественными остатками.');
        $check('Адрес склада', filled($warehouse?->physicalSite?->address ?? $warehouse?->address), 'Укажите адрес склада из справочника.');
        $check('Магазин пилота', $warehouse && \App\Models\Page\Store::where('warehouse_id', $warehouse->id)->where('is_active', true)->exists(), 'Нужен активный магазин, связанный с центральным складом.');
        $check('Тарифы доставки', $warehouse && $warehouse->deliveryProfiles->contains(fn ($profile) => $profile->is_active && $profile->service_tariffs !== null), 'Настройте покрытие и тарифы доставки, включая услуги.');
        $check('Другие склады', \App\Models\Inventory\Warehouse::where('is_active', true)->where('id', '!=', (int) $this->option('warehouse'))->count() === 0, 'Для пилота проверьте и отключите другие источники, если они пока не участвуют.');
        $methods = \App\Models\Payment\PaymentMethod::active()->get();
        $check('Способы оплаты', $methods->isNotEmpty(), 'Включите хотя бы один рабочий способ оплаты.');
        foreach ($methods as $method) {
            if (in_array($method->gateway, ['raiffeisen_ecom', 'raiffeisen_acquiring'], true)) {
                $settings = $method->configuration() ?? [];
                $public = $settings['public_id'] ?? config('payment.raiffeisen_ecom.public_id') ?: config('payment.raiffeisen.public_id');
                $secret = $settings['secret_key'] ?? config('payment.raiffeisen_ecom.secret_key') ?: config('payment.raiffeisen.secret_key');
                $check('Эквайринг '.$method->code, filled($public) && filled($secret), 'Не заданы реквизиты подключения к банку. Их значения в отчёт не выводятся.');
            }
        }
        $check('Принимающий API', config('services.integration_1c.enabled') && filled(config('services.integration_1c.base_url')), 'Включите интеграцию и задайте адрес принимающего API.');
        $queue = config('services.integration_1c.orders_queue');
        $check('Отдельная очередь заказов', $queue !== 'integration-1c' && in_array($queue, config('queue_workers.queues', []), true), 'Обработчик должен слушать отдельную очередь заказов, без старого хвоста импорта.');
        $tick = \Illuminate\Support\Facades\Cache::get('pilot:cooling_last_run');
        $rows[] = ['Обработка охлаждения', $tick && $tick >= now()->subMinutes(5)->timestamp ? 'OK' : 'CHECK', $tick ? 'Последний запуск: '.date('c', $tick) : 'Последний запуск не зафиксирован; проверьте scheduler.'];
        $rows[] = ['Банк и принимающая 1С', 'CHECK', 'Требуются банковский тест и проверка дедупликации на принимающей стороне. Этот отчёт не делает внешних запросов.'];
        $this->table(['Проверка', 'Статус', 'Результат'], $rows);
        return $blocked ? self::FAILURE : self::SUCCESS;
    }
}
