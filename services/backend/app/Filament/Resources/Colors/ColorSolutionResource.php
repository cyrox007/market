<?php

namespace App\Filament\Resources\Colors;

use App\Filament\Resources\Colors\Pages\ManageColorSolutions;
use App\Filament\Resources\Products\ProductResource;
use App\Models\Product\Color\ColorSolution;
use App\Models\Product\Product;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ColorSolutionResource extends Resource
{
    protected static ?string $model = ColorSolution::class;

    protected static ?string $modelLabel = 'Цветовое решение';

    protected static ?string $pluralModelLabel = 'Цветовые решения';

    protected static ?string $navigationLabel = 'Цветовые решения';

    protected static string|UnitEnum|null $navigationGroup = 'Товары';

    protected static ?int $navigationSort = 52;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPaintBrush;

    public static function form(Schema $schema): Schema
    {
        return $schema->components(static::formFields());
    }

    /** Поля решения — общие для раздела и создания на месте. */
    public static function formFields(): array
    {
        return [
            TextInput::make('name')
                ->label('Название')
                ->placeholder('Дуб Вотан/Белый')
                ->required()
                ->maxLength(255),
            ColorSlugInput::make(),
            SpatieMediaLibraryFileUpload::make('icon')
                ->label('Пиктограмма')
                ->collection('icon')
                ->image()
                ->maxSize(2048)
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp']),
            Select::make('baseColors')
                ->label('Базовые цвета')
                ->helperText(ColorFormHint::make('По ним товар находится в фильтре «Цвет».'))
                ->relationship('baseColors', 'name')
                ->multiple()
                ->preload()
                ->saveRelationshipsUsing(function (ColorSolution $record, ?array $state): void {
                    $record->baseColors()->sync($state ?? []);
                    Product::flushAllProductCaches();
                }),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('baseColors')->withCount('rows'))
            ->columns([
                SpatieMediaLibraryImageColumn::make('icon')
                    ->label('Пиктограмма')
                    ->collection('icon')
                    ->conversion('thumb')
                    ->size(40)
                    ->extraImgAttributes(['style' => 'border: 1px solid rgb(0 0 0 / 0.2); border-radius: 0.375rem;']),
                TextColumn::make('name')->label('Название')->searchable()->sortable()->weight('bold')->wrap(),
                TextColumn::make('slug')->label('ЧПУ')->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('baseColors.name')->label('Базовые цвета')->badge()->placeholder('Не заданы'),
                TextColumn::make('rows_count')
                    ->label('Товаров и вариаций')
                    ->sortable()
                    ->url(fn (ColorSolution $record): ?string => $record->rows_count === 0 ? null : ProductResource::getUrl('index', [
                        'filters' => ['color_solution' => ['value' => $record->id]],
                    ]))
                    ->openUrlInNewTab()
                    ->color(fn (int $state): ?string => $state === 0 ? null : 'primary'),
            ])
            ->filters([
                SelectFilter::make('baseColors')
                    ->label('Базовый цвет')
                    ->relationship('baseColors', 'name')
                    ->multiple()
                    ->preload(),
                SelectFilter::make('without_base_colors')
                    ->label('Базовые цвета')
                    ->options(['without' => 'Не заданы'])
                    ->query(fn (Builder $query, array $data) => $data['value'] === 'without'
                        ? $query->whereDoesntHave('baseColors')
                        : $query),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->hidden(fn (ColorSolution $record): bool => $record->rows_count > 0),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageColorSolutions::route('/'),
        ];
    }
}
