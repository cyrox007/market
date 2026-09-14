<?php

namespace App\Filament\Resources\Products\RelationManagers;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;

use function Filament\authorize;

class OperatorProductVariationAttributeSelectionRelationManager extends ProductVariationAttributeSelectionRelationManager
{
    protected static ?string $title = 'Параметры вариаций';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        if ($ownerRecord->isVariant()) {
            return false;
        }

        if (static::shouldSkipAuthorization()) {
            return true;
        }

        if ($relatedResource = static::getRelatedResource()) {
            return $relatedResource::canAccess();
        }

        $model = $ownerRecord->{static::getRelationshipName()}()->getQuery()->getModel()::class;

        try {
            return authorize('viewAny', $model, static::shouldCheckPolicyExistence())->allowed();
        } catch (AuthorizationException $exception) {
            return $exception->toResponse()->allowed();
        }
    }
}
