<?php

namespace App\Filament\Resources\Pages;

use Filament\Actions\Action;

abstract class EditRecord extends \Filament\Resources\Pages\EditRecord
{
    public static bool $formActionsAreSticky = true;

    protected bool $stayAfterSave = false;

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction()
                ->label('Сохранить')
                ->icon('heroicon-m-check'),
            Action::make('saveAndStay')
                ->label('Сохранить и остаться')
                ->icon('heroicon-m-pencil-square')
                ->color('gray')
                ->action('saveAndStay')
                ->keyBindings(['mod+shift+s']),
            $this->getCancelFormAction(),
        ];
    }

    public function saveAndStay(): void
    {
        $this->stayAfterSave = true;
        $this->save();
    }

    protected function getRedirectUrl(): ?string
    {
        return $this->stayAfterSave
            ? null
            : static::getResource()::getUrl('index');
    }
}
