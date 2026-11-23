<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\News\ArticleResource;
use App\Filament\Resources\Newsletter\NewsletterSubscriberResource;
use App\Filament\Resources\Sliders\SliderResource;
use App\Models\News\Article;
use App\Models\Newsletter\NewsletterSubscriber;
use App\Models\Page\Slider;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class ContentStatsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 1;

    protected ?string $heading = 'Контент и публикации';

    protected ?string $description = 'Только доступные разделы и действия';

    public static function canView(): bool
    {
        return DashboardRoleChecks::canSeeContent();
    }

    protected function getStats(): array
    {
        $user = auth()->user();
        $stats = [];

        // Access is checked before querying: never count data from a denied module.
        if ($user->can('viewAny articles')) {
            $count = Cache::remember('dashboard_articles_v3', 120, fn () => Article::count());
            $stats[] = Stat::make('Статей', number_format($count, 0, ',', ' '))
                ->description('Редактировать публикации')
                ->icon(Heroicon::OutlinedNewspaper)
                ->color('gray')
                ->url(ArticleResource::getUrl('index'));
        }
        if ($user->can('viewAny sliders')) {
            $count = Cache::remember('dashboard_sliders_v3', 120, fn () => Slider::count());
            $stats[] = Stat::make('Слайдеров', number_format($count, 0, ',', ' '))
                ->description('Баннеры главной')
                ->icon(Heroicon::OutlinedPhoto)
                ->color('info')
                ->url(SliderResource::getUrl('index'));
        }
        if ($user->can('viewAny newsletter_subscribers')) {
            $count = Cache::remember('dashboard_newsletter_v3', 120, fn () => NewsletterSubscriber::count());
            $stats[] = Stat::make('Подписчиков рассылки', number_format($count, 0, ',', ' '))
                ->description('Управление рассылкой')
                ->icon(Heroicon::OutlinedEnvelope)
                ->color('primary')
                ->url(NewsletterSubscriberResource::getUrl('index'));
        }

        return $stats;
    }
}
