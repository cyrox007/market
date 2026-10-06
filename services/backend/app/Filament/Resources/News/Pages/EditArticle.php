<?php

namespace App\Filament\Resources\News\Pages;

use App\Filament\Resources\News\ArticleResource;
use App\Filament\Resources\Pages\EditRecord;
use Filament\Actions;

class EditArticle extends EditRecord
{
    protected static string $resource = ArticleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    public function getTitle(): string
    {
        return __('filament/admin_sv/edit_article.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament/admin_sv/edit_article.title');
    }
}
