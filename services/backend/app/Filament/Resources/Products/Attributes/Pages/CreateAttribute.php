<?php

namespace App\Filament\Resources\Products\Attributes\Pages;

use App\Filament\Resources\Products\Attributes\AttributeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAttribute extends CreateRecord
{
    protected static string $resource = AttributeResource::class;
}

