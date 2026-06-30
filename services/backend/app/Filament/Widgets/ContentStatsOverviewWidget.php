<?php

namespace App\Filament\Widgets;

use App\Models\News\Article;
use App\Models\Newsletter\NewsletterSubscriber;
use App\Models\Page\InteriorIdea;
use App\Models\Page\Slider;
use App\Models\Page\Store;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Cache;

class ContentStatsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 5;

    protected ?string $heading = 'Контент';

    protected ?string $description = 'Новости, слайдеры и идеи интерьера';

    public static function canView(): bool
    {
        return DashboardRoleChecks::canSeeContent();
    }

    protected function getStats(): array
    {
        $cacheKey = 'dashboard_content_stats_' . (auth()->id() ?? 'guest');
        $stats = Cache::remember($cacheKey, 120, function () {
            return [
                'articles' => Article::count(),
                'sliders' => Slider::count(),
                'interior_ideas' => InteriorIdea::count(),
                'stores' => Store::count(),
                'newsletter' => NewsletterSubscriber::count(),
            ];
        });

        return [
            Stat::make('Статей', number_format($stats['articles'], 0, ',', ' '))
                ->description('Новости и публикации')
                ->descriptionIcon(Heroicon::OutlinedNewspaper)
                ->color('gray')
                ->icon(Heroicon::OutlinedNewspaper),
            Stat::make('Слайдеров', number_format($stats['sliders'], 0, ',', ' '))
                ->description('Баннеры на главной')
                ->descriptionIcon(Heroicon::OutlinedPhoto)
                ->color('info')
                ->icon(Heroicon::OutlinedPhoto),
            Stat::make('Идей интерьера', number_format($stats['interior_ideas'], 0, ',', ' '))
                ->description('Интерьерные подборки')
                ->descriptionIcon(Heroicon::OutlinedHomeModern)
                ->color('success')
                ->icon(Heroicon::OutlinedHomeModern),
            Stat::make('Магазинов', number_format($stats['stores'], 0, ',', ' '))
                ->description('Точки выдачи')
                ->descriptionIcon(Heroicon::OutlinedBuildingStorefront)
                ->color('warning')
                ->icon(Heroicon::OutlinedBuildingStorefront),
            Stat::make('Подписчиков рассылки', number_format($stats['newsletter'], 0, ',', ' '))
                ->description('Email-рассылка')
                ->descriptionIcon(Heroicon::OutlinedEnvelope)
                ->color('primary')
                ->icon(Heroicon::OutlinedEnvelope),
        ];
    }
}
