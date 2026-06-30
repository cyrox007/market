<?php

namespace App\Filament\Resources\Mail\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MailEventForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Основная информация')
                    ->schema([
                        TextInput::make('code')
                            ->label('Код события')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->helperText('Уникальный код события (например, order.created). Используется в коде для отправки писем.')
                            ->disabled(fn($record) => $record !== null),
                        TextInput::make('name')
                            ->label('Название')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Название события для отображения в админке'),
                        Textarea::make('description')
                            ->label('Описание')
                            ->rows(3)
                            ->maxLength(1000)
                            ->helperText('Описание события и его назначения'),
                        Toggle::make('is_active')
                            ->label('Активно')
                            ->default(true)
                            ->helperText('Если выключено, письма по этому событию не будут отправляться'),
                    ])->columns(1),
            ]);
    }
}
