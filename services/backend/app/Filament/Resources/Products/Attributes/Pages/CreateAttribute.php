<?php

namespace App\Filament\Resources\Products\Attributes\Pages;

use App\Filament\Resources\Pages\CreateRecord;
use App\Filament\Resources\Products\Attributes\AttributeResource;

class CreateAttribute extends CreateRecord
{
    protected static string $resource = AttributeResource::class;
}
