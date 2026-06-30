<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductCollectionResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewProductCollection extends ViewRecord
{
    protected static string $resource = ProductCollectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }

    public function getTitle(): string
    {
        return $this->record->name ?? 'Подборка товаров';
    }
}
