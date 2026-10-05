<?php

namespace App\Filament\Resources\Sliders\Pages;

use App\Filament\Resources\Sliders\SliderResource;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;

class EditSlider extends EditRecord
{
    protected static string $resource = SliderResource::class;

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
                ->action('saveAndStay'),
            $this->getCancelFormAction(),
        ];
    }

    public function saveAndStay(): void
    {
        $this->stayAfterSave = true;
        $this->save();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
    public function getTitle(): string
    {
        return __('filament/admin_sv/edit_slider.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament/admin_sv/edit_slider.title');
    }

    protected function getRedirectUrl(): ?string
    {
        return $this->stayAfterSave ? null : SliderResource::getUrl('index');
    }

}


