<?php

namespace App\Filament\Resources\Shipping\DeliveryHandlingTypes\Pages;

use App\Filament\Resources\Shipping\DeliveryHandlingTypes\DeliveryHandlingTypeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDeliveryHandlingType extends EditRecord
{
    protected static string $resource = DeliveryHandlingTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
    public function getTitle(): string
    {
        return __('filament/admin_sv/edit_delivery_handling_type.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament/admin_sv/edit_delivery_handling_type.title');
    }

}
