<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Order\Order;
use App\Support\Integration\OrderOneCSyncDispatcher;
use Illuminate\Console\Command;

class EnqueueOrders1CSyncCommand extends Command
{
    protected $signature = 'orders:enqueue-1c-sync
                            {--order-id= : Отправить конкретный заказ по внутреннему ID}
                            {--status= : Фильтр по статусу (например accepted, awaiting_payment)}
                            {--since= : Только заказы с created_at >= даты (Y-m-d)}
                            {--limit=0 : Максимум заказов (0 = без лимита)}';

    protected $description = 'Поставить в очередь integration-1c отправку заказов в 1С';

    public function handle(): int
    {
        if (! OrderOneCSyncDispatcher::isEnabled()) {
            $this->error('Интеграция 1С выключена (ONEC_API_ENABLED=false).');

            return self::FAILURE;
        }

        $query = Order::query()->orderBy('id');

        if ($orderIdOption = $this->option('order-id')) {
            $orderId = (int) $orderIdOption;
            if ($orderId <= 0) {
                $this->error('--order-id должен быть положительным целым ID заказа.');

                return self::FAILURE;
            }

            $query->whereKey($orderId);
        }

        if ($status = $this->option('status')) {
            $query->where('status', (string) $status);
        }

        if ($since = $this->option('since')) {
            $query->whereDate('created_at', '>=', (string) $since);
        }

        $limit = (int) $this->option('limit');
        if ($limit > 0) {
            $query->limit($limit);
        }

        $count = 0;
        $query->each(function (Order $order) use (&$count): void {
            OrderOneCSyncDispatcher::dispatch($order);
            $count++;
        });

        $queue = (string) (
            config('services.integration_1c.orders_queue')
            ?? config('services.onec.orders_queue')
            ?? 'integration-1c'
        );
        $workerQueues = implode(',', array_unique([$queue, 'default']));

        $this->info("В очередь «{$queue}» поставлено заказов: {$count}");
        $this->line("Обработка: php8.4 artisan queue:work --queue={$workerQueues}");

        return self::SUCCESS;
    }
}
