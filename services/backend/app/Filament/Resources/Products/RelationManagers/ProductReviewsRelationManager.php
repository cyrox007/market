<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Filament\Resources\Products\ProductResource;
use App\Models\Product\Review;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Notifications\Notification;

class ProductReviewsRelationManager extends RelationManager
{
    protected static string $relationship = 'reviews';

    protected static ?string $title = 'Отзывы';

    protected static ?string $recordTitleAttribute = 'name';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Информация о пользователе')
                    ->schema([
                        TextInput::make('name')
                            ->label('Имя')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->maxLength(255),
                    ])
                    ->columns(2),

                Section::make('Отзыв')
                    ->schema([
                        Select::make('rating')
                            ->label('Рейтинг')
                            ->options([
                                1 => '1 звезда',
                                2 => '2 звезды',
                                3 => '3 звезды',
                                4 => '4 звезды',
                                5 => '5 звезд',
                            ])
                            ->required()
                            ->default(5),

                        Textarea::make('comment')
                            ->label('Комментарий')
                            ->required()
                            ->minLength(10)
                            ->maxLength(5000)
                            ->rows(5)
                            ->columnSpanFull(),

                        Toggle::make('is_approved')
                            ->label('Одобрен')
                            ->default(true) // По умолчанию одобрены при создании в админке
                            ->helperText('Одобренные отзывы отображаются на сайте'),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function ($query) {
                $ownerRecord = $this->getOwnerRecord();

                // Если это вариация, показываем отзывы родительского товара
                if ($ownerRecord->isVariant() && $ownerRecord->parent_product_id) {
                    return \App\Models\Product\Review::query()
                        ->where('product_id', $ownerRecord->parent_product_id)
                        ->with('product');
                }

                // Для обычных товаров показываем их отзывы
                return $query->with('product');
            })
            ->columns([
                TextColumn::make('product.name')
                    ->label('Товар')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn(Review $record): string => $record->product?->sku ?? '—')
                    ->url(fn(Review $record): ?string => $record->product ? ProductResource::getUrl('view', ['record' => $record->product]) : null)
                    ->openUrlInNewTab(false)
                    ->icon(fn(Review $record): ?string => $record->product ? 'heroicon-o-arrow-top-right-on-square' : null)
                    ->iconPosition('after')
                    ->color('primary')
                    ->default('Товар удален'),

                TextColumn::make('name')
                    ->label('Имя автора')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn(Review $record): string => $record->email ?: '—'),

                TextColumn::make('rating')
                    ->label('Рейтинг')
                    ->badge()
                    ->color(fn(int $state): string => match ($state) {
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
                    ->label('Комментарий')
                    ->limit(80)
                    ->wrap()
                    ->tooltip(fn(Review $record): string => $record->comment)
                    ->searchable(),

                TextColumn::make('created_at')
                    ->label('Дата создания')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(),

                IconColumn::make('is_approved')
                    ->label('Статус модерации')
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
            ->headerActions([
                CreateAction::make()
                    ->label('Добавить отзыв')
                    ->icon('heroicon-o-plus')
                    ->mutateFormDataUsing(function (array $data): array {
                        $ownerRecord = $this->getOwnerRecord();

                        // Если это вариация, привязываем отзыв к родительскому товару
                        if ($ownerRecord->isVariant() && $ownerRecord->parent_product_id) {
                            $data['product_id'] = $ownerRecord->parent_product_id;
                        } else {
                            $data['product_id'] = $ownerRecord->id;
                        }

                        // По умолчанию одобряем отзывы, созданные в админке
                        if (!isset($data['is_approved'])) {
                            $data['is_approved'] = true;
                        }
                        return $data;
                    }),
            ])
            ->actions([
                EditAction::make()
                    ->mutateFormDataUsing(function (array $data, Review $record): array {
                        return $data;
                    })
                    ->after(function (Review $record) {
                        Notification::make()
                            ->title('Отзыв обновлен')
                            ->success()
                            ->send();
                    }),

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
                    ->visible(fn(Review $record) => !$record->is_approved),

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
                    ->visible(fn(Review $record) => $record->is_approved),

                DeleteAction::make(),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
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

                    \Filament\Actions\DeleteBulkAction::make()
                        ->label('Удалить выбранные'),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Нет отзывов')
            ->emptyStateDescription('Отзывы появятся здесь после того, как пользователи оставят их на сайте.')
            ->emptyStateIcon('heroicon-o-chat-bubble-left-right');
    }
}