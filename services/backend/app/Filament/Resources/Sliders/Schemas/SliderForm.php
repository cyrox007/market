<?php

namespace App\Filament\Resources\Sliders\Schemas;

use App\Filament\Support\LucideIconSelect;
use App\Models\Page\Slider;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
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
                Section::make('Где показывать')
                    ->description('На главной два независимых блока: верхний промо-блок и нижний широкоформатный баннер.')
                    ->schema([
                        Select::make('placement')
                            ->label('Блок главной')
                            ->options(Slider::placementLabels())
                            ->default(Slider::PLACEMENT_TOP)
                            ->required()
                            ->live(),

                        Select::make('slot')
                            ->label('Место в верхнем блоке')
                            ->options(Slider::slotLabels())
                            ->default(Slider::SLOT_MAIN)
                            ->required()
                            ->visible(fn ($get): bool => $get('placement') === Slider::PLACEMENT_TOP)
                            ->helperText('Основная карусель видна на desktop и mobile. Боковые карточки — дополнительный desktop-контент справа от неё.'),

                        TextInput::make('priority')
                            ->label(__('filament/admin_sv/slider_resource.priority'))
                            ->numeric()
                            ->default(0)
                            ->helperText('Чем меньше число, тем раньше элемент показывается внутри своего блока.'),

                        Toggle::make('is_active')
                            ->label(__('filament/admin_sv/slider_resource.is_active'))
                            ->default(true),
                    ])
                    ->columns(2),

                Section::make('Контент')
                    ->description('Текстовые поля одинаково используются обоими слайдерами. Нижний баннер обычно короче и содержит CTA-кнопку.')
                    ->schema([
                        TextInput::make('title')
                            ->label(__('filament/admin_sv/slider_resource.title'))
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, $set): void {
                                if (! $state) {
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
                            ->maxLength(2048)
                            ->helperText('Можно указать внутренний путь (/catalog/...) или полный URL.'),

                        TextInput::make('button_text')
                            ->label(__('filament/admin_sv/slider_resource.button_text'))
                            ->maxLength(255)
                            ->helperText('Для нижнего баннера обычно «Подробнее». Для верхней основной карусели кнопка может отсутствовать.'),
                    ])
                    ->columns(2),

                Section::make('Метка / бейдж')
                    ->description('Верхний блок использует цветные промо-метки, а нижний баннер может использовать это поле как дату/период акции.')
                    ->schema([
                        TextInput::make('badge_text')
                            ->label(__('filament/admin_sv/slider_resource.badge_text'))
                            ->maxLength(255)
                            ->placeholder('Сезонная распродажа до −60%'),

                        Select::make('badge_tone')
                            ->label('Цвет метки')
                            ->options(Slider::badgeToneLabels())
                            ->default('red'),

                        TextInput::make('badge_link')
                            ->label(__('filament/admin_sv/slider_resource.badge_link'))
                            ->maxLength(2048),

                        LucideIconSelect::make('badge_icon')
                            ->label(__('filament/admin_sv/slider_resource.badge_icon'))
                            ->default('zap')
                            ->helperText('Необязательно. Фронтенд может использовать иконку там, где это предусмотрено макетом.'),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Section::make('Изображения')
                    ->description('Desktop и mobile могут иметь разный кадр. Если мобильная картинка не задана, API отдаст desktop-картинку как fallback.')
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('image')
                            ->collection('image')
                            ->label('Desktop / основное изображение')
                            ->image()
                            ->imageEditor()
                            ->conversion('thumb')
                            ->maxSize(10240)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->columnSpanFull(),

                        TextInput::make('image_url')
                            ->label('Внешний URL / demo fallback')
                            ->maxLength(2048)
                            ->helperText('Используется только если изображение выше не загружено. Удобно для демо-данных и временных материалов.')
                            ->columnSpanFull(),

                        SpatieMediaLibraryFileUpload::make('mobile_image')
                            ->collection('mobile_image')
                            ->label('Отдельное мобильное изображение')
                            ->image()
                            ->imageEditor()
                            ->conversion('thumb')
                            ->maxSize(10240)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->columnSpanFull(),

                        TextInput::make('mobile_image_url')
                            ->label('Внешний URL mobile / demo fallback')
                            ->maxLength(2048)
                            ->helperText('Если не задано ни mobile-изображение, ни этот URL, используется desktop.')
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),

                Section::make('SEO настройки')
                    ->description('Необязательные мета-данные конкретного промо-материала.')
                    ->schema([
                        SEO::make(),
                    ])
                    ->collapsible(),
            ]);
    }
}
