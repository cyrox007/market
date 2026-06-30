<?php

namespace App\Filament\Resources\About\RelationManagers;

use App\Models\Page\TeamMember;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Schemas\Components\Section;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class TeamMembersRelationManager extends RelationManager
{
    protected static string $relationship = 'teamMembers';

    protected static ?string $title = null;

    protected static ?string $recordTitleAttribute = 'name';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('filament/admin_sv/team_members_relation_manager.основнаяинформация'))
                    ->schema([
                        TextInput::make('name')
                            ->label(__('filament/admin_sv/team_members_relation_manager.name'))
                            ->required()
                            ->maxLength(255),
                        TextInput::make('position')
                            ->label(__('filament/admin_sv/team_members_relation_manager.position'))
                            ->required()
                            ->maxLength(255),
                        TextInput::make('priority')
                            ->label(__('filament/admin_sv/team_members_relation_manager.priority'))
                            ->numeric()
                            ->default(0)
                            ->helperText('Чем меньше число, тем выше в списке'),
                    ])->columns(2),
                Section::make(__('filament/admin_sv/team_members_relation_manager.фото'))
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('image')
                            ->collection('image')
                            ->label('Фото')
                            ->image()
                            ->imageEditor()
                            ->conversion('thumb')
                            ->maxSize(10240)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                SpatieMediaLibraryImageColumn::make('image')
                    ->collection('image')
                    ->conversion('thumb')
                    ->label('Фото')
                    ->circular(),
                TextColumn::make('name')
                    ->label(__('filament/admin_sv/team_members_relation_manager.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('position')
                    ->label(__('filament/admin_sv/team_members_relation_manager.position'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('priority')
                    ->label(__('filament/admin_sv/team_members_relation_manager.priority'))
                    ->sortable(),
            ])
            ->filters([])
            ->headerActions([
                CreateAction::make(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([])
            ->defaultSort('priority');
    }
    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('filament/admin_sv/team_members_relation_manager.title');
    }

}

