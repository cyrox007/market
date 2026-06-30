<?php

namespace App\Filament\Resources\About;

use App\Filament\Resources\About\Pages\EditAboutPage;
use App\Filament\Resources\About\Pages\ListAboutPage;
use App\Filament\Resources\About\RelationManagers\AdvantagesRelationManager;
use App\Filament\Resources\About\RelationManagers\TeamMembersRelationManager;
use App\Filament\Resources\About\Schemas\AboutPageForm;
use App\Models\Page\AboutPage;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class AboutPageResource extends Resource
{
    protected static ?string $model = AboutPage::class;

    protected static ?string $navigationLabel = null;

    protected static ?string $modelLabel = null;

    protected static ?string $pluralModelLabel = null;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInformationCircle;

    protected static string|UnitEnum|null $navigationGroup = 'Контент';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return AboutPageForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('hero_title')
                    ->label(__('filament/admin_sv/about_page_resource.hero_title'))
                    ->searchable(),
                TextColumn::make('story_title')
                    ->label(__('filament/admin_sv/about_page_resource.story_title'))
                    ->searchable(),
                IconColumn::make('is_active')
                    ->label(__('filament/admin_sv/about_page_resource.is_active'))
                    ->boolean(),
            ])
            ->filters([])
            ->actions([])
            ->bulkActions([])
            ->defaultSort('id');
    }

    public static function getRelations(): array
    {
        return [
            TeamMembersRelationManager::class,
            AdvantagesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAboutPage::route('/'),
            'edit' => EditAboutPage::route('/{record}/edit'),
        ];
    }

    public static function shouldRegisterNavigation(): bool
    {
        return true;
    }

    public static function shouldSkipAuthorization(): bool
    {
        return true;
    }

    public static function resolveRecordRouteBinding(int|string $key, ?\Closure $modifyQuery = null): ?Model
    {
        $aboutPage = AboutPage::getInstance();

        if (!$aboutPage->exists) {
            $aboutPage->save();
        }

        return $aboutPage;
    }
    public static function getNavigationLabel(): string
    {
        return __('filament/admin_sv/about_page_resource.navigation_label');
    }
    public static function getModelLabel(): string
    {
        return __('filament/admin_sv/about_page_resource.model_label');
    }
    public static function getPluralModelLabel(): string
    {
        return __('filament/admin_sv/about_page_resource.plural_model_label');
    }



}
