<?php

namespace App\Filament\Resources\Shipping\DeliveryHandlingTypes\Pages;

use App\Filament\Resources\Shipping\DeliveryHandlingTypes\DeliveryHandlingTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDeliveryHandlingTypes extends ListRecords
{
    protected static string $resource = DeliveryHandlingTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
    public function getTitle(): string
    {
        return __('filament/admin_sv/list_delivery_handling_types.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament/admin_sv/list_delivery_handling_types.title');
    }

}
