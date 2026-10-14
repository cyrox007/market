<?php

namespace App\Filament\Resources\Stores\Pages;

use App\Filament\Resources\Pages\EditRecord;
use App\Filament\Resources\Stores\StoreResource;
use Filament\Actions\DeleteAction;

class EditStore extends EditRecord
{
    protected static string $resource = StoreResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['store_stock_mode'] = empty($data['warehouse_id']) ? 'showroom' : 'warehouse';

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return app(\App\Services\Address\PhysicalSiteSetup::class)->store($data, $this->getRecord());
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    public function getTitle(): string
    {
        return __('filament/admin_sv/edit_store.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament/admin_sv/edit_store.title');
    }
}
