<?php
namespace App\Filament\Resources\PrivatePriceLinks\Pages;
class CreatePrivatePriceLink extends \App\Filament\Resources\Pages\CreateRecord
{
    protected static string $resource = \App\Filament\Resources\PrivatePriceLinks\PrivatePriceLinkResource::class;
    protected function afterCreate(): void
    {
        \Filament\Notifications\Notification::make()->title('Сохраните ссылку для объявления')
            ->body(url('/offer/'.$this->record->rotateToken()))->persistent()->success()->send();
    }
}
