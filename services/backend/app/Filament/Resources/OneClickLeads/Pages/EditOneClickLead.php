<?php

namespace App\Filament\Resources\OneClickLeads\Pages;

use App\Filament\Resources\OneClickLeads\OneClickLeadResource;
use App\Filament\Resources\Pages\EditRecord;

class EditOneClickLead extends EditRecord
{
    protected static string $resource = OneClickLeadResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Manager changes only workflow status; nested disabled fields must not replace snapshots.
        return ['status' => $data['status']];
    }
}
