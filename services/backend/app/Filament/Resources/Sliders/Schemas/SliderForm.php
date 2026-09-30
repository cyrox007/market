<?php

namespace App\Filament\Resources\Sliders\Schemas;

use App\Filament\Support\LucideIconSelect;
use App\Models\Page\Slider;
use App\Models\Product\Category;
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
                    ->description('На главной используются два управляемых блока: главный промо-слайдер и карусель категорий.')
                    ->schema([
                        Select::make('placement')
                            ->label('Блок')
                            ->options(Slider::placementLabels())
                            ->default(Slider::PLACEMENT_HOME_HERO)
                            ->required()
                            ->live(),

                        Select::make('category_id')
                            ->label('Категория')
                            ->options(fn (): array => Category::query()
                                ->root()
                                ->active()
                                ->ordered()
                                ->pluck('name', 'id')
                                ->toArray())
                            ->searchable()
                            ->preload()
                            ->required(fn ($get): bool => $get('placement') === Slider::PLACEMENT_HOME_CATEGORIES)
                            ->visible(fn ($get): bool => $get('placement') === Slider::PLACEMENT_HOME_CATEGORIES)
                            ->helperText('Карточка возьмёт название, ссылку и изображение из категории. Изображение ниже можно использовать как переопределение.')
                            ->afterStateUpdated(function ($state, $set): void {
                                if (! $state) {
                                    return;
                                }

                                $category = Category::query()->find($state);
                                if (! $category) {
                                    return;
                                }

                                $set('title', (string) $category->name);
                            }),

                        TextInput::make('priority')
                            ->label(__('filament/admin_sv/slider_resource.priority'))
                            ->numeric()
                            ->default(0)
                            ->helperText('Чем меньше число, тем раньше элемент показывается в своём слайдере.'),

                        Toggle::make('is_active')
                            ->label(__('filament/admin_sv/slider_resource.is_active'))
                            ->default(true),
                    ])
                    ->columns(2),

                Section::make('Содержимое промо-слайда')
                    ->description('Текст и переход для большого слайдера главной страницы.')
                    ->schema([
                        TextInput::make('title')
                            ->label(__('filament/admin_sv/slider_resource.title'))
                            ->required(fn ($get): bool => $get('placement') === Slider::PLACEMENT_HOME_HERO)
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
                            ->maxLength(255)
                            ->helperText('Можно указать внутренний путь (/catalog/...) или полный URL.'),

                        TextInput::make('button_text')
                            ->label(__('filament/admin_sv/slider_resource.button_text'))
                            ->maxLength(255),
                    ])
                    ->columns(2)
                    ->visible(fn ($get): bool => $get('placement') === Slider::PLACEMENT_HOME_HERO),

                Section::make(__('filament/admin_sv/slider_resource.badge_section'))
                    ->description(__('filament/admin_sv/slider_resource.badge_section_description'))
                    ->schema([
                        TextInput::make('badge_text')
                            ->label(__('filament/admin_sv/slider_resource.badge_text'))
                            ->maxLength(255)
                            ->placeholder('Сезонная распродажа до −60%'),

                        TextInput::make('badge_link')
                            ->label(__('filament/admin_sv/slider_resource.badge_link'))
                            ->maxLength(255),

                        LucideIconSelect::make('badge_icon')
                            ->label(__('filament/admin_sv/slider_resource.badge_icon'))
                            ->default('zap')
                            ->helperText('Выберите иконку Lucide для бейджа.'),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->visible(fn ($get): bool => $get('placement') === Slider::PLACEMENT_HOME_HERO),

                Section::make('Изображения')
                    ->description('Для промо-слайдера можно задать отдельный мобильный кадр. Для карусели категорий изображения необязательны: по умолчанию используется изображение категории.')
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('image')
                            ->collection('image')
                            ->label('Основное изображение')
                            ->helperText(fn ($get): string => $get('placement') === Slider::PLACEMENT_HOME_CATEGORIES
                                ? 'Необязательно. Если не загружено, используется изображение выбранной категории.'
                                : 'Изображение для desktop/tablet.')
                            ->image()
                            ->imageEditor()
                            ->conversion('thumb')
                            ->maxSize(10240)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->columnSpanFull(),

                        SpatieMediaLibraryFileUpload::make('mobile_image')
                            ->collection('mobile_image')
                            ->label('Мобильное изображение')
                            ->helperText('Необязательно. Если не задано, используется основное изображение.')
                            ->image()
                            ->imageEditor()
                            ->conversion('thumb')
                            ->maxSize(10240)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),

                Section::make('SEO настройки')
                    ->description('Управление мета данными промо-слайда.')
                    ->schema([
                        SEO::make(),
                    ])
                    ->collapsible()
                    ->visible(fn ($get): bool => $get('placement') === Slider::PLACEMENT_HOME_HERO),
            ]);
    }
}
