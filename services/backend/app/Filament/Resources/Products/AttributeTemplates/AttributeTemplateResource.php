<?php

namespace App\Filament\Resources\Products\AttributeTemplates;

use App\Filament\Resources\Products\Attributes\AttributeQuickCreate;
use App\Filament\Resources\Products\AttributeTemplates\Pages\CreateAttributeTemplate;
use App\Filament\Resources\Products\AttributeTemplates\Pages\EditAttributeTemplate;
use App\Filament\Resources\Products\AttributeTemplates\Pages\ListAttributeTemplates;
use App\Models\Product\Attribute;
use App\Models\Product\AttributeTemplate;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class AttributeTemplateResource extends Resource
{
    protected static ?string $model = AttributeTemplate::class;

    protected static ?string $navigationLabel = 'Шаблоны характеристик';

    protected static ?string $modelLabel = 'Шаблон характеристик';

    protected static ?string $pluralModelLabel = 'Шаблоны характеристик';

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $policy = \App\Policies\AttributeTemplatePolicy::class;

    protected static string|UnitEnum|null $navigationGroup = 'Товары';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        TextInput::make('name')
                            ->label('Название')
                            ->placeholder('Диваны, Шкафы-купе, Столы…')
                            ->required()
                            ->maxLength(255),

                        Repeater::make('items')
                            ->label('Характеристики')
                            ->relationship('items')
                            ->orderColumn('sort_order')
                            ->reorderable()
                            ->simple(
                                Select::make('attribute_id')
                                    ->label('Характеристика')
                                    ->options(function (Get $get, $state) {
                                        $takenByOtherRows = array_diff(array_filter(array_column($get('../../items') ?? [], 'attribute_id')), [$state]);

                                        return Attribute::query()
                                            ->assignableToProduct(null)
                                            ->whereNotIn('id', $takenByOtherRows)
                                            ->orderBy('sort_order')
                                            ->orderBy('name')
                                            ->pluck('name', 'id');
                                    })
                                    ->searchable()
                                    ->required()
                                    ->distinct()
                                    ->live()
                                    ->createOptionForm(AttributeQuickCreate::form())
                                    ->createOptionUsing(fn (array $data) => AttributeQuickCreate::create($data)),
                            )
                            ->defaultItems(0)
                            ->addActionLabel('Добавить характеристику'),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Название')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('items_count')
                    ->label('Характеристик')
                    ->counts('items')
                    ->sortable(),

                TextColumn::make('applied_count')
                    ->label('Применений')
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAttributeTemplates::route('/'),
            'create' => CreateAttributeTemplate::route('/create'),
            'edit' => EditAttributeTemplate::route('/{record}/edit'),
        ];
    }
}
