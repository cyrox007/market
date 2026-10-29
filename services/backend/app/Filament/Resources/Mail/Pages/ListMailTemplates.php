<?php

namespace App\Filament\Resources\Mail\Pages;

use App\Filament\Resources\Mail\MailTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMailTemplates extends ListRecords
{
    protected static string $resource = MailTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
