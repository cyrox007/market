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
                Section::make('Расположение на главной')
                    ->description('Сначала выберите, в какой части главной страницы должен появиться материал.')
                    ->schema([
                        Select::make('placement')
                            ->label('Какой блок редактируем?')
                            ->options([
                                Slider::PLACEMENT_TOP => 'Верхний блок — большой слайдер и две карточки справа',
                                Slider::PLACEMENT_BOTTOM => 'Нижний блок — широкий баннер-слайдер',
                            ])
                            ->default(Slider::PLACEMENT_TOP)
                            ->required()
                            ->live()
                            ->helperText('На мобильной версии у верхнего блока показывается только большой слайдер; две маленькие карточки справа скрыты.'),

                        Select::make('slot')
                            ->label('Куда поставить материал в верхнем блоке?')
                            ->options([
                                Slider::SLOT_MAIN => 'Большой слайдер слева',
                                Slider::SLOT_RIGHT_TOP => 'Маленькая карточка справа — сверху',
                                Slider::SLOT_RIGHT_BOTTOM => 'Маленькая карточка справа — снизу',
                            ])
                            ->default(Slider::SLOT_MAIN)
                            ->required()
                            ->live()
                            ->visible(fn ($get): bool => $get('placement') === Slider::PLACEMENT_TOP)
                            ->helperText(fn ($get): string => match ($get('slot')) {
                                Slider::SLOT_RIGHT_TOP => 'Фиксированное место: если включить новый материал, предыдущий активный материал в верхней правой карточке будет выключен.',
                                Slider::SLOT_RIGHT_BOTTOM => 'Фиксированное место: если включить новый материал, предыдущий активный материал в нижней правой карточке будет выключен.',
                                default => 'Это большая карусель: здесь можно иметь несколько активных слайдов, они идут по порядку.',
                            }),

                        TextInput::make('priority')
                            ->label('Порядок показа')
                            ->numeric()
                            ->default(0)
                            ->visible(fn ($get): bool =>
                                $get('placement') === Slider::PLACEMENT_BOTTOM
                                || $get('slot') === Slider::SLOT_MAIN
                            )
                            ->helperText('Для слайдеров с несколькими материалами: чем меньше число, тем раньше материал показывается.'),

                        Toggle::make('is_active')
                            ->label('Показывать на сайте')
                            ->default(true),
                    ])
                    ->columns(2),

                Section::make('Содержимое')
                    ->description('Заполните то, что посетитель увидит на баннере или карточке.')
                    ->schema([
                        TextInput::make('title')
                            ->label('Заголовок')
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
                            ->label('Системный адрес')
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),

                        RichEditor::make('description')
                            ->label('Подзаголовок / описание')
                            ->maxLength(1000)
                            ->columnSpanFull(),

                        TextInput::make('link')
                            ->label('Куда вести по нажатию')
                            ->maxLength(2048)
                            ->helperText('Можно указать внутренний путь (/catalog/...) или полный URL.'),

                        TextInput::make('button_text')
                            ->label('Текст кнопки')
                            ->maxLength(255)
                            ->helperText('Для нижнего баннера обычно «Подробнее». Для верхней основной карусели кнопка может отсутствовать.'),
                    ])
                    ->columns(2),

                Section::make('Промо-метка')
                    ->description('Например: «Выгодно», «Гарантия», «10 — 31 августа» или «Сезонная распродажа до −60%».')
                    ->schema([
                        TextInput::make('badge_text')
                            ->label('Текст метки')
                            ->maxLength(255)
                            ->placeholder('Сезонная распродажа до −60%'),

                        Select::make('badge_tone')
                            ->label('Цвет метки')
                            ->options(Slider::badgeToneLabels())
                            ->default('red'),

                        TextInput::make('badge_link')
                            ->label('Ссылка с метки')
                            ->maxLength(2048),

                        LucideIconSelect::make('badge_icon')
                            ->label('Иконка метки')
                            ->default('zap')
                            ->helperText('Необязательно. Фронтенд может использовать иконку там, где это предусмотрено макетом.'),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Section::make('Изображения для компьютера и телефона')
                    ->description('Можно загрузить отдельную картинку для телефона — это особенно полезно для узкого мобильного кадра. Если её не задать, будет использована основная.')
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('image')
                            ->collection('image')
                            ->label('Основное изображение')
                            ->image()
                            ->imageEditor()
                            ->conversion('thumb')
                            ->maxSize(10240)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->columnSpanFull(),

                        TextInput::make('image_url')
                            ->label('Временная ссылка на основное изображение')
                            ->maxLength(2048)
                            ->helperText('Необязательно. Используется только если файл выше не загружен; удобно для тестового контента.')
                            ->columnSpanFull(),

                        SpatieMediaLibraryFileUpload::make('mobile_image')
                            ->collection('mobile_image')
                            ->label('Изображение для телефона')
                            ->image()
                            ->imageEditor()
                            ->conversion('thumb')
                            ->maxSize(10240)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->columnSpanFull(),

                        TextInput::make('mobile_image_url')
                            ->label('Временная ссылка на изображение для телефона')
                            ->maxLength(2048)
                            ->helperText('Необязательно. Если ничего не указать, на телефоне будет использовано основное изображение.')
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
