<?php

namespace App\Filament\Resources\Sliders\Schemas;

use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use RalphJSmit\Filament\SEO\SEO;

class SliderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Основная информация')
                    ->schema([
                        TextInput::make('title')
                            ->label(__('filament/admin_sv/slider_resource.title'))
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, $set) {
                                if (!$state) {
                                    return;
                                }
                                $set('slug', \Str::slug($state));
                            }),
                        TextInput::make('slug')
                            ->label(__('filament/admin_sv/slider_resource.slug'))
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        RichEditor::make('description')
                            ->label(__('filament/admin_sv/slider_resource.description'))
                            ->maxLength(1000)
                            ->columnSpanFull(),
                        TextInput::make('link')
                            ->label(__('filament/admin_sv/slider_resource.link'))
                            ->url()
                            ->maxLength(255),
                        TextInput::make('button_text')
                            ->label(__('filament/admin_sv/slider_resource.button_text'))
                            ->maxLength(255),
                        TextInput::make('priority')
                            ->label(__('filament/admin_sv/slider_resource.priority'))
                            ->numeric()
                            ->default(0)
                            ->helperText('Чем меньше число, тем выше в списке'),
                        Toggle::make('is_active')
                            ->label(__('filament/admin_sv/slider_resource.is_active'))
                            ->default(true),
                    ])->columns(2),
                Section::make(__('filament/admin_sv/slider_resource.badge_section'))
                    ->description(__('filament/admin_sv/slider_resource.badge_section_description'))
                    ->schema([
                        TextInput::make('badge_text')
                            ->label(__('filament/admin_sv/slider_resource.badge_text'))
                            ->maxLength(255)
                            ->placeholder('Помощь онлайн'),
                        TextInput::make('badge_link')
                            ->label(__('filament/admin_sv/slider_resource.badge_link'))
                            ->url()
                            ->maxLength(255),
                        TextInput::make('badge_icon')
                            ->label(__('filament/admin_sv/slider_resource.badge_icon'))
                            ->maxLength(255)
                            ->default('ri-flashlight-fill')
                            ->helperText('Класс иконки Remix Icon, например: ri-flashlight-fill, ri-customer-service-2-line'),
                    ])->columns(2)->collapsible(),
                Section::make('SEO настройки')
                    ->description('Управление мета данными')
                    ->schema([
                        SEO::make()
                    ])
                    ->collapsible(),
                Section::make('Изображение')
                    ->description('Загрузка изображения слайдера. Изображение автоматически оптимизируется и создаются миниатюры')
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('image')
                            ->collection('image')
                            ->label('Изображение')
                            ->helperText('Основное изображение слайдера. Будет автоматически создана миниатюра 300x300px')
                            ->image()
                            ->imageEditor()
                            ->conversion('thumb')
                            ->maxSize(10240)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),
            ]);
    }
}


