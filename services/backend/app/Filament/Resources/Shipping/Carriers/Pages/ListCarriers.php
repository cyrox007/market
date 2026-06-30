<?php

namespace App\Filament\Resources\Shipping\Carriers\Pages;

use App\Filament\Resources\Shipping\Carriers\CarrierResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCarriers extends ListRecords
{
    protected static string $resource = CarrierResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
    public function getTitle(): string
    {
        return __('filament/admin_sv/list_carriers.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament/admin_sv/list_carriers.title');
    }

}
