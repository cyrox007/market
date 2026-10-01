<?php

namespace App\Filament\Resources\Sliders\Pages;

use App\Filament\Resources\Sliders\SliderResource;
use App\Models\Page\Slider;
use Database\Seeders\SliderDemoSeeder;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListSliders extends ListRecords
{
    protected static string $resource = SliderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('createDemoContent')
                ->label('Добавить демо-контент')
                ->icon('heroicon-o-sparkles')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Добавить демо-слайды для главной?')
                ->modalDescription('Добавятся только отсутствующие demo-записи для верхнего и нижнего блоков. Уже существующие записи и ваши изменения не будут перезаписаны.')
                ->action(function (): void {
                    $before = Slider::query()
                        ->where('slug', 'like', 'demo-%')
                        ->count();

                    app(SliderDemoSeeder::class)->run();

                    $after = Slider::query()
                        ->where('slug', 'like', 'demo-%')
                        ->count();

                    Notification::make()
                        ->title('Демо-контент подготовлен')
                        ->body('Добавлено записей: ' . max(0, $after - $before) . '.')
                        ->success()
                        ->send();
                }),

            Actions\CreateAction::make()
                ->label('Добавить слайд'),
        ];
    }

    public function getTitle(): string
    {
        return __('filament/admin_sv/list_sliders.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament/admin_sv/list_sliders.title');
    }
}
