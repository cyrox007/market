<?php

namespace App\Filament\Resources\CatalogCardIssues;

use App\Filament\Resources\Products\ProductResource;
use App\Models\CatalogCardIssue;
use App\Services\Catalog\CatalogCardQuality;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CatalogCardIssuesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('product.name')
                    ->label('Карточка')
                    ->weight('bold')
                    ->searchable()
                    ->description(fn (CatalogCardIssue $record) => $record->product
                        ? CatalogCardQuality::typeLabel($record->product) : 'Товар удалён')
                    ->wrap(),
                TextColumn::make('critical_count')
                    ->label('Критично')
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'gray')
                    ->sortable(),
                TextColumn::make('warning_count')
                    ->label('Проверить')
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'warning' : 'gray')
                    ->sortable(),
                TextColumn::make('reason_labels')
                    ->label('Причины')
                    ->state(fn (CatalogCardIssue $record): array => $record->reason_labels)
                    ->listWithLineBreaks()
                    ->wrap(),
                TextColumn::make('checked_at')
                    ->label('Проверено')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('severity')
                    ->label('Приоритет')
                    ->options([
                        'critical' => 'Есть критичные проблемы',
                        'warnings' => 'Несколько замечаний',
                    ])
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'critical' => $query->where('critical_count', '>', 0),
                        'warnings' => $query->where('critical_count', 0)->where('warning_count', '>=', 2),
                        default => $query,
                    }),
            ])
            ->recordUrl(fn (CatalogCardIssue $record) => $record->product
                && (ProductResource::canEdit($record->product) || ProductResource::canView($record->product))
                ? ProductResource::getUrl(ProductResource::canEdit($record->product) ? 'edit' : 'view',
                    ['record' => $record->product])
                : null)
            ->defaultSort('critical_count', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->orderByDesc('warning_count')->orderBy('product_id'))
            ->emptyStateHeading('Проблемных карточек пока нет')
            ->emptyStateDescription('Проверьте дату последнего аудита: отсутствие записей до первого прохода не означает, что ошибок нет.')
            ->defaultPaginationPageOption(25)
            ->paginationPageOptions([25, 50, 100]);
    }
}
