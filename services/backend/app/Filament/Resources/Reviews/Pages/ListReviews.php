<?php

namespace App\Filament\Resources\Reviews\Pages;

use App\Filament\Resources\Reviews\ReviewResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListReviews extends ListRecords
{
    protected static string $resource = ReviewResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label(__('filament/admin_sv/list_reviews.добавитьотзыв')),
        ];
    }
    public function getTitle(): string
    {
        return __('filament/admin_sv/list_reviews.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament/admin_sv/list_reviews.title');
    }

}
