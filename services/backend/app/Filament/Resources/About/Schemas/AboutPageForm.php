<?php

namespace App\Filament\Resources\About\Schemas;

use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use RalphJSmit\Filament\SEO\SEO;

class AboutPageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Hero секция')
                    ->schema([
                        TextInput::make('hero_title')
                            ->label(__('filament/admin_sv/about_page_resource.hero_title'))
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Textarea::make('hero_description')
                            ->label(__('filament/admin_sv/about_page_resource.hero_description'))
                            ->rows(3)
                            ->columnSpanFull(),
                        SpatieMediaLibraryFileUpload::make('hero_image')
                            ->collection('hero_image')
                            ->label('Изображение Hero')
                            ->image()
                            ->imageEditor()
                            ->conversion('thumb')
                            ->maxSize(10240)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->columnSpanFull(),
                    ]),
                Section::make('История компании')
                    ->schema([
                        TextInput::make('story_title')
                            ->label(__('filament/admin_sv/about_page_resource.story_title'))
                            ->maxLength(255)
                            ->columnSpanFull(),
                        RichEditor::make('story_content')
                            ->label(__('filament/admin_sv/about_page_resource.story_content'))
                            ->toolbarButtons([
                                'bold',
                                'italic',
                                'underline',
                                'link',
                                'bulletList',
                                'orderedList',
                            ])
                            ->columnSpanFull(),
                        SpatieMediaLibraryFileUpload::make('story_images')
                            ->collection('story_images')
                            ->label('Изображения истории')
                            ->image()
                            ->imageEditor()
                            ->conversion('thumb')
                            ->maxSize(10240)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->multiple()
                            ->columnSpanFull(),
                    ]),
                Section::make('Статистика')
                    ->schema([
                        TextInput::make('statistics.years')
                            ->label(__('filament/admin_sv/about_page_resource.statistics.years'))
                            ->numeric()
                            ->default(0),
                        TextInput::make('statistics.stores')
                            ->label(__('filament/admin_sv/about_page_resource.statistics.stores'))
                            ->numeric()
                            ->default(0),
                        TextInput::make('statistics.clients')
                            ->label(__('filament/admin_sv/about_page_resource.statistics.clients'))
                            ->numeric()
                            ->default(0),
                        TextInput::make('statistics.products')
                            ->label(__('filament/admin_sv/about_page_resource.statistics.products'))
                            ->numeric()
                            ->default(0),
                    ])->columns(2),
                Section::make('Настройки')
                    ->schema([
                        TextInput::make('priority')
                            ->label(__('filament/admin_sv/about_page_resource.priority'))
                            ->numeric()
                            ->default(0)
                            ->helperText('Чем меньше число, тем выше в списке'),
                        Toggle::make('is_active')
                            ->label(__('filament/admin_sv/about_page_resource.is_active'))
                            ->default(true),
                    ])->columns(2),
                Section::make('SEO настройки')
                    ->description('Управление мета данными')
                    ->schema([
                        SEO::make()
                    ])
                    ->collapsible(),
            ]);
    }
}

