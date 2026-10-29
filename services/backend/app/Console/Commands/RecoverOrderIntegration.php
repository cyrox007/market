<?php

namespace App\Console\Commands;

use App\Models\Order\Order;
use App\Support\Integration\OrderOneCSyncDispatcher;
use Illuminate\Console\Command;

class RecoverOrderIntegration extends Command
{
    protected $signature = 'orders:recover-integration';
    protected $description = 'Requeue failed or abandoned order deliveries after a 15 minute delay';

    public function handle(): int
    {
        if (! OrderOneCSyncDispatcher::isEnabled() || ! filled(config('services.integration_1c.base_url'))) {
            $this->warn('Order integration is disabled or its destination URL is missing.');
            return self::SUCCESS;
        }
        Order::whereNotNull('cooling_until')->where('cooling_until', '<=', now())
            ->whereNotIn('status', ['new', 'manager_processing', 'awaiting_payment'])
            ->where(fn ($q) => $q->where('status', '!=', 'cancelled')->orWhereNotNull('integration_sent_at'))
            ->where(fn ($q) => $q->whereNull('integration_state')->orWhere('integration_state', '!=', 'sent'))
            ->where('updated_at', '<=', now()->subMinutes(15))->select('id')->chunkById(100, function ($orders): void {
                foreach ($orders as $order) { OrderOneCSyncDispatcher::dispatch($order->id); }
            });
        return self::SUCCESS;
    }
}
