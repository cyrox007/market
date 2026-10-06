<?php

namespace App\Filament\Resources\Sliders\Pages;

use App\Filament\Resources\Pages\EditRecord;
use App\Filament\Resources\Sliders\SliderResource;
use Filament\Actions;

class EditSlider extends EditRecord
{
    protected static string $resource = SliderResource::class;

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
}
