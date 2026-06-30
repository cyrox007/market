<?php

namespace App\Filament\Resources\Categories\Pages;

use App\Filament\Resources\Categories\CategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCategories extends ListRecords
{
    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    protected function getTableQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getTableQuery()->with('parent');
    }
    public function getTitle(): string
    {
        return __('filament/admin_sv/list_categories.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament/admin_sv/list_categories.title');
    }

}
