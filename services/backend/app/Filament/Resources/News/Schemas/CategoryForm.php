<?php

namespace App\Filament\Resources\News\Schemas;

use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use RalphJSmit\Filament\SEO\SEO;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Основная информация')
                    ->schema([
                        TextInput::make('title')
                            ->label(__('filament/admin_sv/category_resource.title'))
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
                            ->relationship('parent', 'title')
                            ->searchable()
                            ->preload()
                            ->reactive()
                            ->afterStateUpdated(function ($state, $set, $get) {
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
                    ])->columns(2),
                Section::make('SEO настройки')
                    ->description('Управление мета данными')
                    ->schema([
                        SEO::make()
                    ])
                    ->collapsible(),
                RichEditor::make('description')
                    ->label(__('filament/admin_sv/category_resource.description'))
                    ->maxLength(3000)
                    ->columnSpanFull(),
            ]);
    }
}


