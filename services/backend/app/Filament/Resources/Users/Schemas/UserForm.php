<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Основная информация')
                    ->schema([
                        TextInput::make('name')
                            ->label(__('filament/admin_sv/user_resource.name'))
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label(__('filament/admin_sv/user_resource.email'))
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        TextInput::make('phone')
                            ->label(__('filament/admin_sv/user_resource.phone'))
                            ->tel()
                            ->maxLength(20),
                        TextInput::make('password')
                            ->label(__('filament/admin_sv/user_resource.password'))
                            ->password()
                            ->dehydrateStateUsing(fn($state) => Hash::make($state))
                            ->dehydrated(fn($state) => filled($state))
                            ->required(fn(string $context): bool => $context === 'create')
                            ->maxLength(255),
                    ])->columns(2),
                Section::make('Роли и разрешения')
                    ->schema([
                        CheckboxList::make('roles')
                            ->label('Роли')
                            ->options(function () {
                                return \Spatie\Permission\Models\Role::orderBy('name')->pluck('name', 'id')->toArray();
                            })
                            ->searchable()
                            ->bulkToggleable()
                            ->gridDirection('row')
                            ->columns(2)
                            ->helperText('Выберите роли для этого пользователя'),
                    ]),
            ]);
    }
}
