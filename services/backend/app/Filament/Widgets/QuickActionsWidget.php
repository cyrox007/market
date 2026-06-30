<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\News\ArticleResource;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Resources\InteriorIdeas\InteriorIdeaResource;
use App\Filament\Resources\Sliders\SliderResource;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;

class QuickActionsWidget extends Widget
{
    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.quick-actions-widget';

    protected ?string $heading = 'Быстрые действия';

    protected ?string $description = 'Переход к разделам и создание записей';

    public static function canView(): bool
    {
        return auth()->check();
    }

    protected function getViewData(): array
    {
        $user = auth()->user();
        $actions = [];

        if ($user->can('viewAny orders')) {
            $actions[] = [
                'label' => 'Все заказы',
                'url' => OrderResource::getUrl('index'),
                'icon' => Heroicon::OutlinedClipboardDocumentList,
                'color' => 'primary',
            ];
            if ($user->can('create orders')) {
                $actions[] = [
                    'label' => 'Создать заказ',
                    'url' => OrderResource::getUrl('create'),
                    'icon' => Heroicon::OutlinedPlusCircle,
                    'color' => 'success',
                ];
            }
        }

        if ($user->can('viewAny products')) {
            $actions[] = [
                'label' => 'Каталог товаров',
                'url' => ProductResource::getUrl('index'),
                'icon' => Heroicon::OutlinedCube,
                'color' => 'info',
            ];
            if ($user->can('create products')) {
                $actions[] = [
                    'label' => 'Добавить товар',
                    'url' => ProductResource::getUrl('create'),
                    'icon' => Heroicon::OutlinedPlusCircle,
                    'color' => 'success',
                ];
            }
        }

        if ($user->can('viewAny users')) {
            $actions[] = [
                'label' => 'Пользователи',
                'url' => UserResource::getUrl('index'),
                'icon' => Heroicon::OutlinedUserGroup,
                'color' => 'gray',
            ];
            if ($user->can('create users')) {
                $actions[] = [
                    'label' => 'Пригласить пользователя',
                    'url' => UserResource::getUrl('create'),
                    'icon' => Heroicon::OutlinedUserPlus,
                    'color' => 'success',
                ];
            }
        }

        if ($user->can('viewAny articles')) {
            $actions[] = [
                'label' => 'Новости',
                'url' => ArticleResource::getUrl('index'),
                'icon' => Heroicon::OutlinedNewspaper,
                'color' => 'warning',
            ];
            if ($user->can('create articles')) {
                $actions[] = [
                    'label' => 'Новая статья',
                    'url' => ArticleResource::getUrl('create'),
                    'icon' => Heroicon::OutlinedPlusCircle,
                    'color' => 'success',
                ];
            }
        }

        if ($user->can('viewAny sliders')) {
            $actions[] = [
                'label' => 'Слайдеры',
                'url' => SliderResource::getUrl('index'),
                'icon' => Heroicon::OutlinedPhoto,
                'color' => 'info',
            ];
            if ($user->can('create sliders')) {
                $actions[] = [
                    'label' => 'Добавить слайдер',
                    'url' => SliderResource::getUrl('create'),
                    'icon' => Heroicon::OutlinedPlusCircle,
                    'color' => 'success',
                ];
            }
        }

        if ($user->can('viewAny interior_ideas')) {
            $actions[] = [
                'label' => 'Идеи интерьера',
                'url' => InteriorIdeaResource::getUrl('index'),
                'icon' => Heroicon::OutlinedHomeModern,
                'color' => 'warning',
            ];
            if ($user->can('create interior_ideas')) {
                $actions[] = [
                    'label' => 'Добавить идею',
                    'url' => InteriorIdeaResource::getUrl('create'),
                    'icon' => Heroicon::OutlinedPlusCircle,
                    'color' => 'success',
                ];
            }
        }

        return [
            'actions' => $actions,
            'heading' => $this->heading,
            'description' => $this->description,
        ];
    }
}
