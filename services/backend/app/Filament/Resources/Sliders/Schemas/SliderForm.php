<?php

namespace App\Filament\Resources\Sliders\Schemas;

use App\Models\Page\Slider;
use App\Models\Product\Category;
use App\Models\Product\Room;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use RalphJSmit\Filament\SEO\SEO;

class SliderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('slider_form')
                    ->tabs([
                        Tab::make('Слайд')
                            ->icon('heroicon-m-photo')
                            ->schema([
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
                    ->description(fn ($get): string => match (true) {
                        $get('placement') === Slider::PLACEMENT_BOTTOM => 'Нижний широкий баннер: метка, заголовок, описание и жёлтая CTA-кнопка.',
                        in_array($get('slot'), [Slider::SLOT_RIGHT_TOP, Slider::SLOT_RIGHT_BOTTOM], true) => 'Маленькая карточка справа: цветная метка, заголовок, короткое описание и текстовая ссылка.',
                        default => 'Большой верхний слайд: промо-метка, крупный заголовок и описание. Весь слайд можно сделать ссылкой.',
                    })
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

                        Textarea::make('description')
                            ->label('Подзаголовок / описание')
                            ->maxLength(1000)
                            ->rows(3)
                            ->helperText('Обычный текст. Переносы строк будут сохранены; HTML не нужен.')
                            ->columnSpanFull(),

                        Select::make('link_type')
                            ->label('Тип ссылки')
                            ->options([
                                'category' => 'Категория каталога',
                                'room' => 'Комната',
                                'manual' => 'Ручной адрес',
                            ])
                            ->default('manual')
                            ->live()
                            ->dehydrated(false)
                            ->afterStateHydrated(function ($record, $set): void {
                                $link = $record?->link;

                                if ($link && str_starts_with($link, '/catalog/')) {
                                    $set('link_type', 'category');
                                    $set('category_link', $link);
                                } elseif ($link && str_starts_with($link, '/rooms/')) {
                                    $set('link_type', 'room');
                                    $set('room_link', $link);
                                }
                            }),

                        Select::make('category_link')
                            ->label('Категория')
                            ->options(fn (): array => Category::query()
                                ->orderBy('name')
                                ->get()
                                ->mapWithKeys(fn (Category $category): array => [
                                    $category->full_path => $category->name,
                                ])
                                ->filter(fn ($label, $path): bool => filled($path))
                                ->all())
                            ->searchable()
                            ->preload()
                            ->required(fn ($get): bool => $get('link_type') === 'category')
                            ->visible(fn ($get): bool => $get('link_type') === 'category')
                            ->dehydrated(false)
                            ->afterStateUpdated(fn ($state, $set) => $set('link', $state)),

                        Select::make('room_link')
                            ->label('Комната')
                            ->options(fn (): array => Room::query()
                                ->orderBy('name')
                                ->get()
                                ->mapWithKeys(fn (Room $room): array => [
                                    $room->full_path => $room->name,
                                ])
                                ->filter(fn ($label, $path): bool => filled($path))
                                ->all())
                            ->searchable()
                            ->preload()
                            ->required(fn ($get): bool => $get('link_type') === 'room')
                            ->visible(fn ($get): bool => $get('link_type') === 'room')
                            ->dehydrated(false)
                            ->afterStateUpdated(fn ($state, $set) => $set('link', $state)),

                        TextInput::make('link')
                            ->label('Ручной адрес')
                            ->maxLength(2048)
                            ->required(fn ($get): bool =>
                                $get('placement') === Slider::PLACEMENT_BOTTOM
                                || in_array($get('slot'), [Slider::SLOT_RIGHT_TOP, Slider::SLOT_RIGHT_BOTTOM], true)
                            )
                            ->visible(fn ($get): bool => $get('link_type') === 'manual')
                            ->helperText('Можно указать внутренний путь (/catalog/...) или полный URL.'),

                        TextInput::make('button_text')
                            ->label('Текст кнопки')
                            ->maxLength(255)
                            ->required(fn ($get): bool =>
                                $get('placement') === Slider::PLACEMENT_BOTTOM
                                || in_array($get('slot'), [Slider::SLOT_RIGHT_TOP, Slider::SLOT_RIGHT_BOTTOM], true)
                            )
                            ->visible(fn ($get): bool =>
                                $get('placement') === Slider::PLACEMENT_BOTTOM
                                || in_array($get('slot'), [Slider::SLOT_RIGHT_TOP, Slider::SLOT_RIGHT_BOTTOM], true)
                            )
                            ->default('Подробнее')
                            ->helperText('На правых карточках это текстовая ссылка, на нижнем баннере — жёлтая кнопка.'),
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
                            ->default('yellow')
                            ->visible(fn ($get): bool =>
                                $get('placement') === Slider::PLACEMENT_TOP
                                && in_array($get('slot'), [Slider::SLOT_RIGHT_TOP, Slider::SLOT_RIGHT_BOTTOM], true)
                            )
                            ->helperText('Цветная капсула используется только в двух правых карточках.'),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Section::make('Изображения для компьютера и телефона')
                    ->description(fn ($get): string => match (true) {
                        $get('placement') === Slider::PLACEMENT_BOTTOM => 'Основной кадр — широкий, ориентир 1440×260 px. Для телефона лучше загрузить отдельный вертикальный кадр.',
                        in_array($get('slot'), [Slider::SLOT_RIGHT_TOP, Slider::SLOT_RIGHT_BOTTOM], true) => 'Правая карточка имеет ориентир 400×222 px и на мобильной версии не показывается.',
                        default => 'Большой слайд имеет ориентир 824×461 px. Для mobile лучше загрузить отдельный кадр 4:5.',
                    })
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('image')
                            ->collection('image')
                            ->label('Основное изображение')
                            ->image()
                            ->imageEditor()
                            ->imagePreviewHeight('260')
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
                            ->imagePreviewHeight('260')
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
                            ]),

                        Tab::make('SEO')
                            ->icon('heroicon-m-magnifying-glass')
                            ->schema([
                                Section::make('SEO настройки')
                                    ->description('Необязательные мета-данные конкретного промо-материала.')
                                    ->schema([
                                        SEO::make(),
                                    ]),
                            ]),
                    ])
                    ->contained(false)
                    ->persistTab()
                    ->id('slider-form-tabs')
                    ->columnSpanFull(),
            ]);
    }
}
