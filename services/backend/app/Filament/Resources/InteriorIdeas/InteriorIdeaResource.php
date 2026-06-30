<?php

namespace App\Filament\Resources\InteriorIdeas;

use App\Filament\Resources\InteriorIdeas\Pages\CreateInteriorIdea;
use App\Filament\Resources\InteriorIdeas\Pages\EditInteriorIdea;
use App\Filament\Resources\InteriorIdeas\Pages\ListInteriorIdeas;
use App\Filament\Resources\InteriorIdeas\RelationManagers\HotspotsRelationManager;
use App\Filament\Resources\InteriorIdeas\Schemas\InteriorIdeaForm;
use App\Filament\Resources\InteriorIdeas\Tables\InteriorIdeasTable;
use App\Models\Page\InteriorIdea;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class InteriorIdeaResource extends Resource
{
    protected static ?string $model = InteriorIdea::class;

    protected static ?string $navigationLabel = 'Идеи для интерьера';

    protected static ?string $modelLabel = 'Идея для интерьера';

    protected static ?string $pluralModelLabel = 'Идеи для интерьера';

    protected static ?string $policy = \App\Policies\InteriorIdeaPolicy::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected static string|UnitEnum|null $navigationGroup = 'Контент';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return InteriorIdeaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InteriorIdeasTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            HotspotsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInteriorIdeas::route('/'),
            'create' => CreateInteriorIdea::route('/create'),
            'edit' => EditInteriorIdea::route('/{record}/edit'),
        ];
    }
}
