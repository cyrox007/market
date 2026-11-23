<?php

namespace App\Filament\Resources\CatalogCardIssues;

use App\Filament\Resources\CatalogCardIssues\Pages\ListCatalogCardIssues;
use App\Models\CatalogCardIssue;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class CatalogCardIssueResource extends Resource
{
    protected static ?string $model = CatalogCardIssue::class;

    protected static ?string $navigationLabel = 'Карточки требуют внимания';

    protected static ?string $modelLabel = 'Проверка карточки';

    protected static ?string $pluralModelLabel = 'Карточки требуют внимания';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static string|UnitEnum|null $navigationGroup = 'Товары';

    protected static ?int $navigationSort = 80;

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->can('viewAny products');
    }

    public static function canView($record): bool
    {
        return static::canViewAny();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->fromLatestCompleted()->with('product');
    }

    public static function table(Table $table): Table
    {
        return CatalogCardIssuesTable::configure($table);
    }

    public static function getPages(): array
    {
        return ['index' => ListCatalogCardIssues::route('/')];
    }
}
