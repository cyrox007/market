<?php

namespace App\Filament\Resources\Mail\Pages;

use App\Filament\Resources\Mail\MailEventResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMailEvents extends ListRecords
{
    protected static string $resource = MailEventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [];
    }
}