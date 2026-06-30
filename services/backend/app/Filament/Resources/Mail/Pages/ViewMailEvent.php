<?php

namespace App\Filament\Resources\Mail\Pages;

use App\Filament\Resources\Mail\MailEventResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewMailEvent extends ViewRecord
{
    protected static string $resource = MailEventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }

    public function mount(int | string $record): void
    {
        parent::mount($record);
        
        // Загружаем связи для отображения
        $this->record->load([
            'templates',
            'logs',
        ]);
    }
}
