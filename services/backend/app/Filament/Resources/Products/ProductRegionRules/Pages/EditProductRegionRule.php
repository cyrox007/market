<?php

namespace App\Filament\Resources\Products\ProductRegionRules\Pages;

use App\Filament\Resources\Products\ProductRegionRules\ProductRegionRuleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProductRegionRule extends EditRecord
{
    protected static string $resource = ProductRegionRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
