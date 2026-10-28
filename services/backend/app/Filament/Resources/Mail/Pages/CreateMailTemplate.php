<?php

namespace App\Filament\Resources\Mail\Pages;

use App\Filament\Resources\Mail\MailTemplateResource;
use App\Filament\Resources\Mail\Schemas\MailTemplateForm;
use App\Filament\Resources\Pages\CreateRecord;

class CreateMailTemplate extends CreateRecord
{
    protected static string $resource = MailTemplateResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['variables'] = MailTemplateForm::variablesForEventId($data['mail_event_id'] ?? null);

        return $data;
    }
}
