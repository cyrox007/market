<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductCollectionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProductCollection extends CreateRecord
{
    protected static string $resource = ProductCollectionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $data;
    }

    protected function afterCreate(): void
    {
        $record = $this->record;
        
        // Если подборка автоматическая, синхронизируем товары по скоупу
        if ($record->is_auto && $record->scope_type) {
            $record->syncProductsByScope();
        } elseif ($record->is_auto) {
            \Filament\Notifications\Notification::make()
                ->title('Укажите тип скоупа')
                ->body('Подборка создана, но товары не заполнены: не выбран тип скоупа.')
                ->warning()
                ->send();
        }
    }
}
