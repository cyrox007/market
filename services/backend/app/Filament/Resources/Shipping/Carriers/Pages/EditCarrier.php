<?php

namespace App\Filament\Resources\Shipping\Carriers\Pages;

use App\Filament\Resources\Shipping\Carriers\CarrierResource;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Vanilo\Shipment\Models\ShippingMethod;

class EditCarrier extends EditRecord
{
    protected static string $resource = CarrierResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->action(function ($record): void {
                    $linkedMethodsCount = ShippingMethod::query()
                        ->where('carrier_id', $record->id)
                        ->count();

                    if ($linkedMethodsCount > 0) {
                        Notification::make()
                            ->title('Нельзя удалить службу доставки')
                            ->body("К службе привязано способов доставки: {$linkedMethodsCount}. Сначала отвяжите или удалите их.")
                            ->danger()
                            ->send();

                        return;
                    }

                    $record->delete();

                    Notification::make()
                        ->title('Служба доставки удалена')
                        ->success()
                        ->send();

                    $this->redirect(CarrierResource::getUrl('index'));
                }),
        ];
    }
    public function getTitle(): string
    {
        return __('filament/admin_sv/edit_carrier.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament/admin_sv/edit_carrier.title');
    }

}
