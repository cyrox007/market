<?php

namespace App\Filament\Resources\Pages;

use Filament\Actions\Action;

abstract class CreateRecord extends \Filament\Resources\Pages\CreateRecord
{
    public static bool $formActionsAreSticky = true;

    protected bool $stayAfterSave = false;

    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction()
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
        $this->create();
    }

    protected function getRedirectUrl(): string
    {
        if ($this->stayAfterSave) {
            return static::getResource()::getUrl('edit', [
                'record' => $this->getRecord()->getKey(),
            ]);
        }

        return static::getResource()::getUrl('index');
    }
}
