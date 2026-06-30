<?php

namespace App\Filament\Resources\About\Pages;

use App\Filament\Resources\About\AboutPageResource;
use App\Models\Page\AboutPage;
use Filament\Resources\Pages\EditRecord;

class EditAboutPage extends EditRecord
{
    protected static string $resource = AboutPageResource::class;

    protected ?string $heading = 'Редактирование страницы "О нас"';

    protected function resolveRecord(int|string $key): \Illuminate\Database\Eloquent\Model
    {
        $aboutPage = AboutPage::getInstance();

        if (!$aboutPage->exists) {
            $aboutPage->save();
        }

        return $aboutPage;
    }
}
