<?php

namespace App\Filament\Resources\News\Pages;

use App\Filament\Resources\News\ArticleResource;
use App\Filament\Resources\Pages\CreateRecord;

class CreateArticle extends CreateRecord
{
    protected static string $resource = ArticleResource::class;
}
