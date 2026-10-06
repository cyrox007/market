<?php

namespace App\Filament\Resources\Mail\Pages;

use App\Filament\Resources\Mail\MailEventResource;
use App\Filament\Resources\Pages\CreateRecord;

class CreateMailEvent extends CreateRecord
{
    protected static string $resource = MailEventResource::class;
}
