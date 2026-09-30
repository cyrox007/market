<?php

namespace App\Filament\Resources\Sliders\Pages;

use App\Filament\Resources\Sliders\SliderResource;
use App\Models\Page\Slider;
use App\Models\Product\Category;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListSliders extends ListRecords
{
    protected static string $resource = SliderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('bootstrapCategoryCarousel')
                ->label('Заполнить карусель категориями')
                ->icon('heroicon-o-squares-plus')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Добавить корневые категории в карусель?')
                ->modalDescription('Будут созданы только отсутствующие элементы. Уже настроенные элементы не изменятся.')
                ->action(function (): void {
                    $categories = Category::query()
                        ->root()
                        ->active()
                        ->ordered()
                        ->get();

                    $created = 0;

                    foreach ($categories as $index => $category) {
                        $slider = Slider::query()->firstOrCreate(
                            [
                                'placement' => Slider::PLACEMENT_HOME_CATEGORIES,
                                'category_id' => $category->id,
                            ],
                            [
                                'title' => (string) $category->name,
                                'slug' => 'home-category-' . $category->slug,
                                'priority' => $index * 10,
                                'is_active' => true,
                            ]
                        );

                        if ($slider->wasRecentlyCreated) {
                            $created++;
                        }
                    }

                    Notification::make()
                        ->title('Карусель категорий подготовлена')
                        ->body("Добавлено элементов: {$created}. Всего активных корневых категорий: {$categories->count()}.")
                        ->success()
                        ->send();
                }),

            Actions\CreateAction::make()
                ->label('Добавить элемент'),
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


