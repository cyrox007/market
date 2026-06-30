<?php

namespace App\Filament\Resources\InteriorIdeas\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class InteriorIdeaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Основная информация')
                    ->schema([
                        TextInput::make('title')
                            ->label('Название')
                            ->maxLength(255)
                            ->helperText('Название идеи для интерьера (опционально)')
                            ->columnSpanFull(),

                        Toggle::make('is_active')
                            ->label('Активна')
                            ->default(true)
                            ->helperText('Показывать идею на главной странице'),

                        TextInput::make('priority')
                            ->label('Приоритет')
                            ->numeric()
                            ->default(0)
                            ->helperText('Чем меньше число, тем выше идея в списке. По умолчанию: 0'),
                    ])
                    ->columns(2),

                Section::make('Изображение интерьера')
                    ->description('Загрузка изображения интерьера. Изображение автоматически оптимизируется')
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('image')
                            ->collection('image')
                            ->label('Изображение интерьера')
                            ->helperText('Основное изображение интерьера. Будет автоматически создана миниатюра 300x300px и основное изображение 820x880px')
                            ->image()
                            ->imageEditor()
                            ->conversion('thumb')
                            ->maxSize(10240)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->required()
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),
            ]);
    }
}
