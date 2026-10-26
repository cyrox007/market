<?php

namespace App\Console\Commands;

use App\Models\Order\Order;
use App\Services\Order\OrderCooling;
use Illuminate\Console\Command;

class AdvanceOrderCooling extends Command
{
    protected $signature = 'orders:advance-cooling';
    protected $description = 'Finish expired order cooling periods and release expired cart holds';

    public function handle(OrderCooling $cooling): int
    {
        Order::where('status', 'manager_processing')->whereNotNull('cooling_until')->where('cooling_until', '<=', now())
            ->select('id')->chunkById(100, function ($rows) use ($cooling): void {
                foreach ($rows as $row) { $cooling->advance($row->id); }
            });
        if (\Illuminate\Support\Facades\DB::table('cart_stock_reservations')->where('expires_at', '<=', now())->delete()) {
            \Illuminate\Support\Facades\Cache::forever('fulfillment_revision', (string) \Illuminate\Support\Str::uuid());
        }
        return self::SUCCESS;
    }
}
