<?php

namespace App\Filament\Resources\Products\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;

class OperatorProductVariantsRelationManager extends ProductVariantsRelationManager
{
    protected static ?string $title = 'Вариации';

    public static function canViewForRecord($ownerRecord, string $pageClass): bool
    {
        if ($ownerRecord->isVariant()) {
            return false;
        }

        return RelationManager::canViewForRecord($ownerRecord, $pageClass);
    }
}
