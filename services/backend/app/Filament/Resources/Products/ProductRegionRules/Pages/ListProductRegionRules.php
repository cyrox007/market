<?php

namespace App\Filament\Resources\Products\ProductRegionRules\Pages;

use App\Filament\Resources\Products\ProductRegionRules\ProductRegionRuleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProductRegionRules extends ListRecords
{
    protected static string $resource = ProductRegionRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
