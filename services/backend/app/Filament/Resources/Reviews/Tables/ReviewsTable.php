<?php

namespace App\Filament\Resources\Reviews\Tables;

use App\Filament\Resources\Products\ProductResource;
use App\Models\Product\Review;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Notifications\Notification;

class ReviewsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function ($query) {
                return $query->with('product');
            })
            ->columns([
                TextColumn::make('product.name')
                    ->label(__('filament/admin_sv/review_resource.product.name'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (Review $record): string => $record->product?->sku ?? '—')
                    ->url(fn (Review $record): ?string => $record->product ? ProductResource::getUrl('view', ['record' => $record->product]) : null)
                    ->openUrlInNewTab(false)
                    ->icon(fn (Review $record): ?string => $record->product ? 'heroicon-o-arrow-top-right-on-square' : null)
                    ->iconPosition('after')
                    ->color('primary')
                    ->default('Товар удален'),

                TextColumn::make('name')
                    ->label(__('filament/admin_sv/review_resource.name'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (Review $record): string => $record->email ?: '—'),

                TextColumn::make('rating')
                    ->label(__('filament/admin_sv/review_resource.rating'))
                    ->badge()
                    ->color(fn (int $state): string => match ($state) {
                        5 => 'success',
                        4 => 'success',
                        3 => 'warning',
                        2 => 'danger',
                        1 => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(function ($state) {
                        $stars = '';
                        for ($i = 1; $i <= 5; $i++) {
                            $stars .= $i <= $state ? '★' : '☆';
                        }
                        return $stars . ' ' . $state;
                    })
                    ->sortable()
                    ->alignCenter(),

                TextColumn::make('comment')
                    ->label(__('filament/admin_sv/review_resource.comment'))
                    ->limit(80)
                    ->wrap()
                    ->tooltip(fn (Review $record): string => $record->comment)
                    ->searchable(),

                TextColumn::make('created_at')
                    ->label(__('filament/admin_sv/review_resource.created_at'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(),

                IconColumn::make('is_approved')
                    ->label(__('filament/admin_sv/review_resource.is_approved'))
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-clock')
                    ->trueColor('success')
                    ->falseColor('warning')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('is_approved')
                    ->label('Статус модерации')
                    ->options([
                        true => 'Одобренные',
                        false => 'Ожидают модерации',
                    ])
                    ->default(null),

                SelectFilter::make('rating')
                    ->label('Рейтинг')
                    ->options([
                        5 => '5 звезд',
                        4 => '4 звезды',
                        3 => '3 звезды',
                        2 => '2 звезды',
                        1 => '1 звезда',
                    ])
                    ->multiple(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                \Filament\Actions\Action::make('approve')
                    ->label('Одобрить')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (Review $record) {
                        $record->update(['is_approved' => true]);
                        Notification::make()
                            ->title('Отзыв одобрен')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (Review $record) => !$record->is_approved),

                \Filament\Actions\Action::make('reject')
                    ->label('Отклонить')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (Review $record) {
                        $record->update(['is_approved' => false]);
                        Notification::make()
                            ->title('Отзыв отклонен')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (Review $record) => $record->is_approved),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    \Filament\Actions\BulkAction::make('approve')
                        ->label('Одобрить выбранные')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            $count = 0;
                            foreach ($records as $record) {
                                if (!$record->is_approved) {
                                    $record->update(['is_approved' => true]);
                                    $count++;
                                }
                            }
                            Notification::make()
                                ->title("Одобрено отзывов: {$count}")
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    \Filament\Actions\BulkAction::make('reject')
                        ->label('Отклонить выбранные')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            $count = 0;
                            foreach ($records as $record) {
                                if ($record->is_approved) {
                                    $record->update(['is_approved' => false]);
                                    $count++;
                                }
                            }
                            Notification::make()
                                ->title("Отклонено отзывов: {$count}")
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    DeleteBulkAction::make()
                        ->label('Удалить выбранные'),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Нет отзывов')
            ->emptyStateDescription('Отзывы появятся здесь после того, как пользователи оставят их на сайте.')
            ->emptyStateIcon('heroicon-o-chat-bubble-left-right');
    }
}
