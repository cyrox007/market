<?php

namespace App\Filament\Resources\News;

use App\Filament\Resources\News\Pages\CreateCategory;
use App\Filament\Resources\News\Pages\EditCategory;
use App\Filament\Resources\News\Pages\ListCategories;
use App\Filament\Resources\News\Schemas\CategoryForm;
use App\Filament\Resources\News\Tables\CategoriesTable;
use App\Models\News\NewsCategory;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class CategoryResource extends Resource
{
    protected static ?string $model = NewsCategory::class;

    protected static ?string $navigationLabel = null;

    protected static ?string $modelLabel = null;

    protected static ?string $pluralModelLabel = null;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFolder;

    protected static string|UnitEnum|null $navigationGroup = 'Новости';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return CategoryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CategoriesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCategories::route('/'),
            'create' => CreateCategory::route('/create'),
            'edit' => EditCategory::route('/{record}/edit'),
        ];
    }
    public static function getNavigationLabel(): string
    {
        return __('filament/admin_sv/category_resource.navigation_label');
    }
    public static function getModelLabel(): string
    {
        return __('filament/admin_sv/category_resource.model_label');
    }
    public static function getPluralModelLabel(): string
    {
        return __('filament/admin_sv/category_resource.plural_model_label');
    }



}

