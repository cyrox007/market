<?php

namespace App\Filament\Resources\News\Pages;

use App\Filament\Resources\News\ArticleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListArticles extends ListRecords
{
    protected static string $resource = ArticleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
    public function getTitle(): string
    {
        return __('filament/admin_sv/list_articles.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament/admin_sv/list_articles.title');
    }

}


