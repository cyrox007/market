<?php

namespace App\Filament\Resources\Roles;

use App\Filament\Resources\Roles\Pages\CreateRole;
use App\Filament\Resources\Roles\Pages\EditRole;
use App\Filament\Resources\Roles\Pages\ListRoles;
use App\Support\AdminAccessCatalog;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Spatie\Permission\Models\Role;
use UnitEnum;

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Права доступа';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Роли';

    protected static ?string $modelLabel = 'Роль';

    protected static ?string $pluralModelLabel = 'Роли';

    protected static ?string $policy = \App\Policies\RolePolicy::class;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Роль и её назначение')
                ->description('Название роли служит техническим идентификатором и используется в проверках доступа.')
                ->schema([
                    TextInput::make('name')
                        ->label('Системный ключ роли')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(255)
                        ->helperText('Например: manager. Не переименовывайте действующие системные роли без проверки политик доступа.'),
                ]),
            Section::make('Матрица разрешений')
                ->description('Разделы сгруппированы по задачам. Раскройте нужный раздел, затем отметьте конкретные действия. Права суммируются.')
                ->schema(AdminAccessCatalog::permissionSections())
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Роль')
                    ->formatStateUsing(fn (string $state): string => AdminAccessCatalog::roleTitle($state))
                    ->description(fn (Role $record): string => $record->name)
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('permissions_count')
                    ->label('Количество разрешений')
                    ->counts('permissions')
                    ->sortable(),
                TextColumn::make('users_count')
                    ->label('Пользователей')
                    ->counts('users')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Создано')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make(),
            ])
            ->defaultSort('name');
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
            'index' => ListRoles::route('/'),
            'create' => CreateRole::route('/create'),
            'edit' => EditRole::route('/{record}/edit'),
        ];
    }
}
