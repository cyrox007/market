<?php

namespace App\Filament\Resources\News\Schemas;

use App\Models\User;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use RalphJSmit\Filament\SEO\SEO;

class ArticleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Основная информация')
                    ->schema([
                        TextInput::make('title')
                            ->label(__('filament/admin_sv/article_resource.title'))
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
                            ->label(__('filament/admin_sv/article_resource.slug'))
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        RichEditor::make('excerpt')
                            ->label(__('filament/admin_sv/article_resource.excerpt'))
                            ->maxLength(500)
                            ->helperText('Краткое описание статьи для карточек и списков (до 500 символов)')
                            ->columnSpanFull(),
                        RichEditor::make('content')
                            ->label(__('filament/admin_sv/article_resource.content'))
                            ->helperText('Полное содержание статьи')
                            ->columnSpanFull(),
                        Select::make('category_id')
                            ->label(__('filament/admin_sv/article_resource.category_id'))
                            ->relationship('category', 'title')
                            ->searchable()
                            ->preload()
                            ->nullable(),
                        Select::make('author_id')
                            ->label(__('filament/admin_sv/article_resource.author_id'))
                            ->relationship('author', 'name')
                            ->searchable()
                            ->preload()
                            ->default(__('filament/admin_sv/article_resource.author_id_default'))
                            ->nullable(),
                        DateTimePicker::make('published_at')
                            ->label(__('filament/admin_sv/article_resource.published_at'))
                            ->nullable(),
                        TextInput::make('priority')
                            ->label(__('filament/admin_sv/article_resource.priority'))
                            ->numeric()
                            ->default(0)
                            ->helperText('Чем меньше число, тем выше в списке'),
                        Toggle::make('is_active')
                            ->label(__('filament/admin_sv/article_resource.is_active'))
                            ->default(true),
                    ])->columns(2),
                Section::make('SEO настройки')
                    ->description('Управление мета данными')
                    ->schema([
                        SEO::make()
                    ])
                    ->collapsible(),
                Section::make('Изображения статьи')
                    ->description('Загрузка главного изображения и галереи. Изображения автоматически оптимизируются и создаются миниатюры')
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('image')
                            ->collection('image')
                            ->label('Главное изображение')
                            ->helperText('Основное изображение статьи, отображаемое в карточке и на странице статьи. Будет автоматически создана миниатюра 300x300px')
                            ->image()
                            ->imageEditor()
                            ->conversion('thumb')
                            ->maxSize(10240)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->columnSpanFull(),
                        SpatieMediaLibraryFileUpload::make('gallery')
                            ->collection('gallery')
                            ->label('Галерея изображений')
                            ->helperText('Дополнительные изображения статьи для галереи. Можно загрузить до 10 изображений. Для каждого будет создана миниатюра')
                            ->multiple()
                            ->image()
                            ->imageEditor()
                            ->conversion('thumb')
                            ->maxFiles(10)
                            ->maxSize(10240)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),
            ]);
    }
}


