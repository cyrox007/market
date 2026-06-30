<?php

namespace App\Filament\Resources\Categories\RelationManagers;

use App\Models\Product\ProductDeliveryBlock;
use Filament\Actions\AttachAction;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CategoryDeliveryBlocksRelationManager extends RelationManager
{
    protected static string $relationship = 'deliveryBlocks';

    protected static ?string $title = 'Блоки доставки';

    protected static ?string $recordTitleAttribute = 'title';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Заголовок')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('description')
                    ->label('Описание')
                    ->searchable()
                    ->limit(100)
                    ->wrap()
                    ->toggleable(),
                TextColumn::make('icon')
                    ->label('Иконка')
                    ->badge()
                    ->color('gray')
                    ->toggleable(),
                TextColumn::make('pivot.sort_order')
                    ->label('Порядок')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Активен')
                    ->boolean(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                AttachAction::make()
                    ->label('Привязать блок')
                    ->preloadRecordSelect()
                    ->recordSelectOptionsQuery(fn ($query) => $query->where('is_active', true))
                    ->form(fn (AttachAction $action): array => [
                        $action->getRecordSelect()
                            ->label('Блок доставки')
                            ->required(),
                        TextInput::make('sort_order')
                            ->label('Порядок сортировки')
                            ->numeric()
                            ->default(0)
                            ->required(),
                    ])
                    ->mutateFormDataUsing(function (array $data): array {
                        // Убеждаемся, что sort_order установлен
                        if (!isset($data['sort_order'])) {
                            $data['sort_order'] = 0;
                        }
                        return $data;
                    }),
            ])
            ->actions([
                EditAction::make()
                    ->label('Изменить порядок')
                    ->form([
                        TextInput::make('sort_order')
                            ->label('Порядок сортировки')
                            ->numeric()
                            ->default(fn ($record) => $record->pivot->sort_order ?? 0)
                            ->required(),
                    ])
                    ->using(function (array $data, $record): void {
                        // Обновляем данные в pivot таблице
                        $this->getOwnerRecord()->deliveryBlocks()->updateExistingPivot($record->id, [
                            'sort_order' => $data['sort_order'] ?? 0,
                        ]);
                    }),
                DetachAction::make()
                    ->label('Отвязать'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('update_sort_order')
                        ->label('Изменить порядок')
                        ->form([
                            TextInput::make('sort_order')
                                ->label('Новый порядок сортировки')
                                ->numeric()
                                ->default(0)
                                ->required(),
                        ])
                        ->action(function ($records, array $data): void {
                            foreach ($records as $record) {
                                $this->getOwnerRecord()->deliveryBlocks()->updateExistingPivot($record->id, [
                                    'sort_order' => $data['sort_order'],
                                ]);
                            }
                        })
                        ->deselectRecordsAfterCompletion(),
                    DetachBulkAction::make()
                        ->label('Отвязать выбранные'),
                ]),
            ])
            ->modifyQueryUsing(function ($query) {
                $query->orderBy('category_delivery_blocks.sort_order', 'asc');
            });
    }
}
