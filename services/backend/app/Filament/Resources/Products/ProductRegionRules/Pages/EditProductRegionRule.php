<?php

namespace App\Filament\Resources\Products\ProductRegionRules\Pages;

use App\Filament\Resources\Pages\EditRecord;
use App\Filament\Resources\Products\ProductRegionRules\ProductRegionRuleResource;
use Filament\Actions\DeleteAction;

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
