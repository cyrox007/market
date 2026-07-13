<?php

namespace App\Filament\Resources\Products\Attributes;

use App\Filament\Resources\Products\Attributes\Pages\ListAttributes;
use App\Filament\Resources\Products\Attributes\Pages\CreateAttribute;
use App\Filament\Resources\Products\Attributes\Pages\EditAttribute;
use App\Models\Product\Attribute;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Support\Icons\Heroicon;
use BackedEnum;
use UnitEnum;

class AttributeResource extends Resource
{
    protected static ?string $model = Attribute::class;

    protected static ?string $navigationLabel = null;

    protected static ?string $modelLabel = null;

    protected static ?string $pluralModelLabel = null;

    protected static ?string $policy = \App\Policies\AttributePolicy::class;

    protected static string|UnitEnum|null $navigationGroup = 'Товары';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Основные данные')
                    ->description('Название и идентификатор атрибута')
                    ->schema([
                        TextInput::make('name')
                            ->label(__('filament/admin_sv/attribute_resource.name'))
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, callable $set) {
                                if (!$state) {
                                    return;
                                }
                                $set('slug', \Str::slug($state));
                            })
                            ->placeholder('Цвет, Размер, Материал, Комплектация…'),

                        TextInput::make('slug')
                            ->label(__('filament/admin_sv/attribute_resource.slug'))
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->placeholder('color, size, material')
                            ->helperText('Только латиница, цифры и дефис. Оставьте пустым для автоматической генерации.'),
                    ])
                    ->columns(2),

                Section::make('Тип и отображение')
                    ->description('Как атрибут ведёт себя в каталоге и в карточке товара')
                    ->schema([
                        Select::make('type')
                            ->label(__('filament/admin_sv/attribute_resource.type'))
                            ->options([
                                'select' => 'Список',
                                'color' => 'Цвет',
                                'string' => 'Строка',
                                'text' => 'Текст',
                                'number' => 'Число (список)',
                                'number_input' => 'Число (ввод)',
                            ])
                            ->default('select')
                            ->required()
                            ->live()
                            ->helperText('От типа зависят форма ввода значений ниже и вид на сайте.'),

                        TextInput::make('sort_order')
                            ->label(__('filament/admin_sv/attribute_resource.sort_order'))
                            ->numeric()
                            ->default(0)
                            ->helperText('Порядок в списках: меньше число — выше в списке'),
                    ])
                    ->columns(2),

                Section::make('Использование')
                    ->description('Где и как атрибут участвует в каталоге и вариациях')
                    ->schema([
                        Toggle::make('is_use_in_variations')
                            ->label('Участвует в торговых предложениях')
                            ->default(false)
                            ->helperText('Параметр вариации: цвет, размер, комплект и т.д.')
                            ->live(),

                        Toggle::make('is_filterable')
                            ->label(__('filament/admin_sv/attribute_resource.is_filterable'))
                            ->default(true)
                            ->helperText('Показывать в фильтрах каталога'),

                        Toggle::make('is_required')
                            ->label('Обязательная характеристика')
                            ->default(false)
                            ->helperText('Если включено, при сохранении товара с этой категорией значение должно быть заполнено.'),

                        Toggle::make('is_multiple') 
                            ->label('Множественный выбор')
                            ->default(false)
                            ->helperText('Разрешить выбор нескольких значений (например, для цвета)')
                            ->visible(fn ($get) => $get('type') === 'color' || in_array($get('type'), ['select', 'string'], true)),
                            
                        Toggle::make('allow_custom_value')
                            ->label('Разрешить свой текст в вариациях')
                            ->default(false)
                            ->helperText('В форме вариации можно ввести произвольное значение в дополнение к списку (например, свой комплект). Имеет смысл для типов «Выбор из списка» и «Строка».')
                            ->visible(fn ($get) => $get('is_use_in_variations') && in_array($get('type'), ['select', 'string'], true)),
                    ])
                    ->columns(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('filament/admin_sv/attribute_resource.name'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('slug')
                    ->label(__('filament/admin_sv/attribute_resource.slug'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('type')
                    ->label('Тип')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'color' => 'info',
                        'string' => 'warning',
                        'select' => 'success',
                        'number' => 'gray',
                        'text' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'text' => 'Текст',
                        'string' => 'Строка',
                        'color' => 'Цвет',
                        'select' => 'Выбор',
                        'number' => 'Число',
                        default => $state,
                    }),

                IconColumn::make('is_filterable')
                    ->label(__('filament/admin_sv/attribute_resource.is_filterable'))
                    ->boolean()
                    ->sortable(),

                IconColumn::make('is_required')
                    ->label('Обязательная')
                    ->boolean()
                    ->sortable(),

                IconColumn::make('is_use_in_variations')
                    ->label('В вариациях')
                    ->boolean()
                    ->sortable(),

                IconColumn::make('allow_custom_value')
                    ->label('Свой текст')
                    ->boolean()
                    ->sortable(),
                
                IconColumn::make('is_multiple')
                    ->label('Множественный')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('values_count')
                    ->label(__('filament/admin_sv/attribute_resource.values_count'))
                    ->counts('values')
                    ->sortable(),

                TextColumn::make('sort_order')
                    ->label(__('filament/admin_sv/attribute_resource.sort_order'))
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('sort_order');
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Resources\Products\Attributes\RelationManagers\AttributeValuesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAttributes::route('/'),
            'create' => CreateAttribute::route('/create'),
            'edit' => EditAttribute::route('/{record}/edit'),
        ];
    }
    public static function getNavigationLabel(): string
    {
        return __('filament/admin_sv/attribute_resource.navigation_label');
    }
    public static function getModelLabel(): string
    {
        return __('filament/admin_sv/attribute_resource.model_label');
    }
    public static function getPluralModelLabel(): string
    {
        return __('filament/admin_sv/attribute_resource.plural_model_label');
    }
}
