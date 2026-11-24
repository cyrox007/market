<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Support\AdminAccessCatalog;
use App\Support\PhoneNumber;
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
                        TextInput::make('first_name')
                            ->label('Имя (отдельное поле)')
                            ->maxLength(255)
                            ->helperText('Заполняйте вместе с фамилией; старое ФИО не разбивается автоматически.'),
                        TextInput::make('last_name')
                            ->label('Фамилия (отдельное поле)')
                            ->maxLength(255),
                        TextInput::make('patronymic')
                            ->label('Отчество (необязательно)')
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
                            ->maxLength(20)
                            // Нормализуем к канону +7XXXXXXXXXX — единый формат для уникальности и 1С.
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn ($state, callable $set) => $set('phone', PhoneNumber::normalize($state)))
                            ->dehydrateStateUsing(fn ($state) => PhoneNumber::normalize($state))
                            ->unique(ignoreRecord: true),
                        TextInput::make('password')
                            ->label(__('filament/admin_sv/user_resource.password'))
                            ->password()
                            ->dehydrateStateUsing(fn($state) => Hash::make($state))
                            ->dehydrated(fn($state) => filled($state))
                            ->required(fn(string $context): bool => $context === 'create')
                            ->maxLength(255),
                    ])->columns(2),
                Section::make('Доступ сотрудника')
                    ->description('Назначьте роли по должностным обязанностям. Права всех выбранных ролей суммируются.')
                    ->schema([
                        CheckboxList::make('roles')
                            ->label('Назначенные роли')
                            ->options(fn (): array => AdminAccessCatalog::roleOptions())
                            ->disabled(fn (): bool => config('activity_audit.enabled') && ! auth()->user()?->hasRole('super_admin'))
                            ->descriptions(fn (): array => AdminAccessCatalog::roleDescriptions())
                            ->columns(1)
                            ->helperText('Для изменения разрешений откройте раздел «Права доступа → Роли».'),
                    ]),
            ]);
    }
}
