<?php

namespace App\Filament\Resources\Products\Attributes\Pages;

use App\Filament\Resources\Products\Attributes\AttributeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAttribute extends EditRecord
{
    protected static string $resource = AttributeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
    public function getTitle(): string
    {
        return __('filament/admin_sv/edit_attribute.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament/admin_sv/edit_attribute.title');
    }

}

