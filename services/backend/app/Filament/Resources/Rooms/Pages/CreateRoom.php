<?php

namespace App\Filament\Resources\Rooms\Pages;

use App\Filament\Resources\Rooms\RoomResource;
use App\Filament\Resources\Rooms\Schemas\RoomForm;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;

class CreateRoom extends CreateRecord
{
    protected static string $resource = RoomResource::class;

    public static bool $formActionsAreSticky = true;

    protected bool $exitAfterSave = false;

    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction()
                ->label('Сохранить')
                ->icon('heroicon-m-check'),
            Action::make('saveAndExit')
                ->label('Сохранить и выйти')
                ->icon('heroicon-m-arrow-right-start-on-rectangle')
                ->color('gray')
                ->action('saveAndExit')
                ->keyBindings(['mod+shift+s']),
            $this->getCancelFormAction(),
        ];
    }

    public function saveAndExit(): void
    {
        $this->exitAfterSave = true;
        $this->create();
    }

    protected function getRedirectUrl(): string
    {
        if ($this->exitAfterSave) {
            return static::getResource()::getUrl('index');
        }

        return parent::getRedirectUrl();
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return RoomForm::packAttributeFilters($data);
    }
}
