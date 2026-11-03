<?php

namespace App\Filament\Resources\Colors;

use App\Filament\Resources\Colors\Pages\ManageBaseColors;
use App\Models\Product\Color\BaseColor;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use UnitEnum;

class BaseColorResource extends Resource
{
    protected static ?string $model = BaseColor::class;

    protected static ?string $modelLabel = 'Базовый цвет';

    protected static ?string $pluralModelLabel = 'Базовые цвета';

    protected static ?string $navigationLabel = 'Базовые цвета';

    protected static string|UnitEnum|null $navigationGroup = 'Товары';

    protected static ?int $navigationSort = 53;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSwatch;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Название')->required()->maxLength(255),
            ColorSlugInput::make(),
            ColorPicker::make('hex')->label('Образец цвета')->hex(),
            TextInput::make('sort_order')->label('Порядок')->numeric()->default(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('hex')
                    ->label('Образец')
                    ->formatStateUsing(fn (string $state): HtmlString|string => preg_match('/^#[0-9a-f]{3,8}$/i', $state) ? new HtmlString(
                        '<span title="' . $state . '" style="display: inline-block; width: 1.5rem; height: 1.5rem; border-radius: 0.375rem; '
                        . 'border: 1px solid rgb(0 0 0 / 0.2); background: ' . $state . ';"></span>'
                    ) : '—')
                    ->placeholder('—'),
                TextColumn::make('name')->label('Название')->searchable()->sortable()->weight('bold')->wrap(),
                TextColumn::make('slug')->label('ЧПУ')->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('solutions_count')
                    ->label('Цветовых решений')
                    ->counts('solutions')
                    ->sortable()
                    ->url(fn (BaseColor $record): ?string => $record->solutions_count === 0 ? null : ColorSolutionResource::getUrl('index', [
                        'filters' => ['baseColors' => ['values' => [$record->id]]],
                    ]))
                    ->openUrlInNewTab()
                    ->color(fn (int $state): ?string => $state === 0 ? null : 'primary'),
                TextColumn::make('sort_order')->label('Порядок')->numeric()->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->hidden(fn (BaseColor $record): bool => $record->solutions_count > 0),
            ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageBaseColors::route('/'),
        ];
    }
}
