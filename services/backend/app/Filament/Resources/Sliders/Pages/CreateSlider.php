<?php

namespace App\Filament\Resources\Sliders\Pages;

use App\Filament\Resources\Sliders\SliderResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;

class CreateSlider extends CreateRecord
{
    protected static string $resource = SliderResource::class;

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
            return SliderResource::getUrl('index');
        }

        return SliderResource::getUrl('edit', ['record' => $this->getRecord()->getKey()]);
    }
}


