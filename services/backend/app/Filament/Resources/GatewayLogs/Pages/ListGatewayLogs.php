<?php

namespace App\Filament\Resources\GatewayLogs\Pages;

use App\Filament\Resources\GatewayLogs\GatewayLogResource;
use Filament\Resources\Pages\ListRecords;

class ListGatewayLogs extends ListRecords
{
    protected static string $resource = GatewayLogResource::class;

    public function getTitle(): string
    {
        return 'Логи шлюзов (оплата, доставка)';
    }
}
