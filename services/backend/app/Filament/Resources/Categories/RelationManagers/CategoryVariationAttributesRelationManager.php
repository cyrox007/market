<?php

namespace App\Filament\Resources\Categories\RelationManagers;

use App\Models\Product\Attribute;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CategoryVariationAttributesRelationManager extends RelationManager
{
    protected static string $relationship = 'variationAttributes';

    protected static ?string $title = 'Атрибуты вариаций по умолчанию';

    protected static ?string $recordTitleAttribute = 'name';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Атрибут')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->label('Тип')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'color' => 'success',
                        'select' => 'info',
                        default => 'gray',
                    }),
            ])
            ->headerActions([
                AttachAction::make()
                    ->label('Добавить атрибут')
                    ->recordSelectOptionsQuery(fn (Builder $query) => Attribute::variationAttributes()->orderBy('sort_order'))
                    ->recordTitle(fn (Attribute $record) => $record->name . ' (' . $record->slug . ')')
                    ->preloadRecordSelect()
                    ->recordSelectSearchColumns(['name', 'slug'])
                    ->successNotificationTitle('Атрибут добавлен'),
            ])
            ->actions([
                DetachAction::make()
                    ->label('Удалить')
                    ->successNotificationTitle('Атрибут удалён'),
            ])
            ->emptyStateHeading('Не выбрано атрибутов')
            ->emptyStateDescription('Добавьте атрибуты вариаций (Цвет, Размер, Комплект и т.д.), которые будут автоматически предвыбраны при создании товаров в этой категории. Товары всё равно смогут иметь свои дополнительные атрибуты.');
    }
}
