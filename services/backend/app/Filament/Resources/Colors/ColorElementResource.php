<?php

namespace App\Filament\Resources\Colors;

use App\Filament\Resources\Colors\Pages\ManageColorElements;
use App\Filament\Resources\Products\ProductResource;
use App\Models\Product\Color\ColorElement;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class ColorElementResource extends Resource
{
    protected static ?string $model = ColorElement::class;

    protected static ?string $modelLabel = 'Элемент';

    protected static ?string $pluralModelLabel = 'Элементы';

    protected static ?string $navigationLabel = 'Элементы';

    protected static string|UnitEnum|null $navigationGroup = 'Товары';

    protected static ?int $navigationSort = 54;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquare3Stack3d;

    public static function form(Schema $schema): Schema
    {
        return $schema->components(static::formFields());
    }

    /** Поля элемента — общие для раздела и создания на месте. */
    public static function formFields(): array
    {
        return [
            TextInput::make('name')
                ->label('Название')
                ->placeholder('Фасад, столешница, корпус…')
                ->required()
                ->maxLength(255),
            ColorSlugInput::make(),
            TextInput::make('filter_name')
                ->label('Название фильтра')
                ->placeholder('Цвет фасада')
                ->helperText(ColorFormHint::make('Заголовок фильтра в каталоге. Пусто — «Цвет: <элемент>».'))
                ->maxLength(255),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Элемент')->searchable()->sortable()->weight('bold')->wrap(),
                TextColumn::make('slug')->label('ЧПУ')->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('filter_name')->label('Название фильтра')->placeholder('—'),
                TextColumn::make('rows_count')
                    ->label('Товаров и вариаций')
                    ->counts('rows')
                    ->sortable()
                    ->url(fn (ColorElement $record): ?string => $record->rows_count === 0 ? null : ProductResource::getUrl('index', [
                        'filters' => ['color_element' => ['value' => $record->id]],
                    ]))
                    ->openUrlInNewTab()
                    ->color(fn (int $state): ?string => $state === 0 ? null : 'primary'),
                TextColumn::make('sort_order')->label('Порядок')->numeric()->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->hidden(fn (ColorElement $record): bool => $record->rows_count > 0),
            ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageColorElements::route('/'),
        ];
    }
}
