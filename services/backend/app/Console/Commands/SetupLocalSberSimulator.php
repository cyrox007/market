<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Payment\PaymentMethod;
use App\Services\Payment\LocalSberSimulator;
use Illuminate\Console\Command;

final class SetupLocalSberSimulator extends Command
{
    protected $signature = 'sber:simulator-setup';
    protected $description = 'Enable the Sber payment method in an isolated local simulator database';

    public function handle(LocalSberSimulator $simulator): int
    {
        $simulator->assertEnabled();
        PaymentMethod::updateOrCreate(['code' => 'card_sberbank'], [
            'name' => 'Тестовая оплата Сбер', 'gateway' => 'sberbank_acquiring',
            'is_active' => true, 'is_enabled' => true, 'is_coming_soon' => false, 'sort_order' => 10,
        ]);
        $this->info('Локальный способ оплаты готов. Реальный банк не используется.');
        return self::SUCCESS;
    }
}
