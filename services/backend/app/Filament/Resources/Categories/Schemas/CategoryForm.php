<?php

namespace App\Filament\Resources\Categories\Schemas;

use App\Models\Product\Category;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use RalphJSmit\Filament\SEO\SEO;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('category_form')
                    ->tabs([
                        Tab::make('Основное')
                            ->icon('heroicon-m-information-circle')
                            ->schema([
                                Section::make('Основная информация')
                                    ->schema([
                                        TextInput::make('name')
                                            ->label(__('filament/admin_sv/category_resource.name'))
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
                                            ->label(__('filament/admin_sv/category_resource.slug'))
                                            ->maxLength(255)
                                            ->unique(ignoreRecord: true),
                                        Select::make('parent_id')
                                            ->label(__('filament/admin_sv/category_resource.parent_id'))
                                            ->relationship('parent', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->reactive()
                                            ->afterStateUpdated(function ($state, $set, $get) {
                                                // Нельзя выбрать саму себя или дочернюю категорию
                                                if ($state && $state == $get('id')) {
                                                    $set('parent_id', null);
                                                }
                                            }),
                                        TextInput::make('priority')
                                            ->label(__('filament/admin_sv/category_resource.priority'))
                                            ->numeric()
                                            ->default(0)
                                            ->helperText('Чем меньше число, тем выше в списке'),
                                        Toggle::make('is_active')
                                            ->label(__('filament/admin_sv/category_resource.is_active'))
                                            ->default(true),
                                    ])
                                    ->columns(2),
                            ]),

                        Tab::make('Описание')
                            ->icon('heroicon-m-document-text')
                            ->schema([
                                Section::make('Описание категории')
                                    ->schema([
                                        RichEditor::make('Описание')
                                            ->label(__('filament/admin_sv/category_resource.описание'))
                                            ->maxLength(3000)
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                        Tab::make('SEO')
                            ->icon('heroicon-m-magnifying-glass')
                            ->schema([
                                Section::make('SEO настройки')
                                    ->description('Управление мета данными')
                                    ->schema([
                                        SEO::make(),
                                    ]),
                            ]),

                        Tab::make('Изображение')
                            ->icon('heroicon-m-photo')
                            ->schema([
                                Section::make('Изображения категории')
                                    ->description('Загрузка главного изображения категории. Изображение автоматически оптимизируется и создаются миниатюры')
                                    ->schema([
                                        SpatieMediaLibraryFileUpload::make('image')
                                            ->collection('image')
                                            ->label('Главное изображение')
                                            ->helperText('Основное изображение категории, отображаемое в карточке категории. Будет автоматически создана миниатюра 300x300px, HD версия 1280x720px и Full HD версия 1920x1080px')
                                            ->image()
                                            ->imageEditor()
                                            ->conversion('thumb')
                                            ->maxSize(10240)
                                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                    ])
                    ->contained(false)
                    ->scrollable(false)
                    ->persistTab()
                    ->id('category-form-tabs')
                    ->columnSpanFull(),
            ]);
    }
}
