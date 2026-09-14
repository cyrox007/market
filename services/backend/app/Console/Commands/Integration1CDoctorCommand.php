<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

class Integration1CDoctorCommand extends Command
{
    protected $signature = 'integration:1c:doctor';

    protected $description = 'Проверить конфигурацию синхронизации заказов с integration API без отправки данных и вывода секретов';

    public function handle(): int
    {
        $configPrefix = (bool) config('services.integration_1c.enabled', false)
            ? 'services.integration_1c'
            : 'services.onec';

        $enabled = (bool) config($configPrefix . '.enabled', false);
        $baseUrl = rtrim((string) config($configPrefix . '.base_url', ''), '/');
        $ordersPath = (string) config($configPrefix . '.orders_path', '/api/v1/integration/1c/orders');
        $apiKeyConfigured = trim((string) config($configPrefix . '.api_key', '')) !== '';
        $ordersQueue = (string) config($configPrefix . '.orders_queue', 'integration-1c');
        $queueConnection = (string) config('queue.default', 'database');
        $queueDriver = (string) config("queue.connections.{$queueConnection}.driver", 'unknown');
        $failedDriver = (string) config('queue.failed.driver', 'unknown');
        $endpoint = $baseUrl !== ''
            ? $baseUrl . '/' . ltrim($ordersPath, '/')
            : '(ONEC_API_BASE_URL не задан)';

        $checks = [
            ['Интеграция включена', $enabled ? 'yes' : 'NO'],
            ['Endpoint', $endpoint],
            ['API key', $apiKeyConfigured ? 'configured' : 'MISSING'],
            ['Queue connection', $queueConnection],
            ['Queue driver', $queueDriver],
            ['Orders queue', $ordersQueue],
            ['Failed jobs driver', $failedDriver],
        ];

        $this->table(['Проверка', 'Значение'], $checks);

        $errors = [];
        if (! $enabled) {
            $errors[] = 'ONEC_API_ENABLED=false';
        }
        if ($baseUrl === '') {
            $errors[] = 'ONEC_API_BASE_URL не задан';
        }
        if ($ordersPath === '') {
            $errors[] = 'ONEC_ORDERS_PATH пуст';
        }
        if (! $apiKeyConfigured) {
            $errors[] = 'ONEC_API_KEY не задан';
        }
        if ($ordersQueue === '') {
            $errors[] = 'ONEC_ORDERS_QUEUE пуст';
        }

        if ($queueDriver === 'sync') {
            $this->warn('QUEUE_CONNECTION=sync: отдельный worker не используется. Для production async delivery обычно нужен database/redis worker.');
        }

        if ($errors !== []) {
            foreach ($errors as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $this->info('Базовая конфигурация 1С/order-sync выглядит корректно. Секреты не выводились.');
        $this->line("Worker должен слушать очередь: {$ordersQueue}");
        $this->line('Следующий шаг: проверить реальный worker, failed jobs и staging order smoke.');

        return self::SUCCESS;
    }
}
