<?php

namespace App\Filament\Widgets;

use App\Models\News\Article;
use App\Models\Newsletter\NewsletterSubscriber;
use App\Models\Page\Slider;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Cache;

class ContentStatsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 5;

    protected ?string $heading = 'Контент';

    protected ?string $description = 'Новости, слайдеры и подписчики рассылки';

    public static function canView(): bool
    {
        return DashboardRoleChecks::canSeeContent();
    }

    protected function getStats(): array
    {
        $cacheKey = 'dashboard_content_stats_v2_' . (auth()->id() ?? 'guest');
        $stats = Cache::remember($cacheKey, 120, function () {
            return [
                'articles' => Article::count(),
                'sliders' => Slider::count(),
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
            Stat::make('Подписчиков рассылки', number_format($stats['newsletter'], 0, ',', ' '))
                ->description('Email-рассылка')
                ->descriptionIcon(Heroicon::OutlinedEnvelope)
                ->color('primary')
                ->icon(Heroicon::OutlinedEnvelope),
        ];
    }
}
