<?php

namespace App\Filament\Resources\News\Pages;

use App\Filament\Resources\News\ArticleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

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


