<?php

namespace App\Filament\Resources\About\Pages;

use App\Filament\Resources\About\AboutPageResource;
use App\Models\Page\AboutPage;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListAboutPage extends ListRecords
{
    protected static string $resource = AboutPageResource::class;

    public function getTitle(): string | Htmlable
    {
        return __('filament/admin_sv/list_about_page.title');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('edit')
                ->label(__('filament/admin_sv/list_about_page.edit'))
                ->url(function () {
                    $aboutPage = AboutPage::getInstance();
                    if (!$aboutPage->exists) {
                        $aboutPage->save();
                    }
                    return AboutPageResource::getUrl('edit', ['record' => $aboutPage->id]);
                }),
        ];
    }

    protected function getTableQuery(): \Illuminate\Database\Eloquent\Builder
    {
        // Возвращаем запрос с singleton записью
        $aboutPage = AboutPage::getInstance();

        // Убеждаемся, что запись существует и сохранена
        if (!$aboutPage->exists) {
            $aboutPage->save();
        }

        // Перезагружаем из БД
        $aboutPage->refresh();

        return AboutPage::query()->where('id', $aboutPage->id);
    }

    public static function getNavigationLabel(): string
    {
        return __('filament/admin_sv/list_about_page.title');
    }

}

