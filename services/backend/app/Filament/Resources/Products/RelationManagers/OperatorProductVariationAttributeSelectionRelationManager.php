<?php

namespace App\Filament\Resources\Products\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;

class OperatorProductVariationAttributeSelectionRelationManager extends ProductVariationAttributeSelectionRelationManager
{
    protected static ?string $title = 'Параметры вариаций';

    public static function canViewForRecord($ownerRecord, string $pageClass): bool
    {
        if ($ownerRecord->isVariant()) {
            return false;
        }

        return RelationManager::canViewForRecord($ownerRecord, $pageClass);
    }
}
