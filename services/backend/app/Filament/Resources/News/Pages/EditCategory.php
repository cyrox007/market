<?php

namespace App\Filament\Resources\News\Pages;

use App\Filament\Resources\News\CategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCategory extends EditRecord
{
    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
    public function getTitle(): string
    {
        return __('filament/admin_sv/edit_category.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament/admin_sv/edit_category.title');
    }

}


