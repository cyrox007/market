<?php

namespace App\Filament\Resources\GatewayLogs;

use App\Filament\Resources\GatewayLogs\Pages\ListGatewayLogs;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\Gateway\GatewayLog;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use BackedEnum;
use UnitEnum;

class GatewayLogResource extends Resource
{
    protected static ?string $model = GatewayLog::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static string|UnitEnum|null $navigationGroup = 'Заказы';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Логи шлюзов';

    protected static ?string $modelLabel = 'Запись лога';

    protected static ?string $pluralModelLabel = 'Логи шлюзов';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Дата')
                    ->dateTime('d.m.Y H:i:s')
                    ->sortable(),
                TextColumn::make('gateway')
                    ->label('Шлюз')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'raiffeisen_acquiring' => 'Райффайзен',
                        'raiffeisen_ecom' => 'Райффайзен e-commerce',
                        'admin_manual' => 'Ручная операция',
                        default => $state,
                    })
                    ->sortable(),
                TextColumn::make('channel')
                    ->label('Канал')
                    ->badge()
                    ->sortable(),
                TextColumn::make('action')
                    ->label('Действие')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'payment_initiated' => 'Платёж создан',
                        'order_created' => 'Заказ создан в API',
                        'order_create_failed' => 'Ошибка создания заказа в API',
                        'callback_received' => 'Получен callback',
                        'callback_success' => 'Оплата успешна',
                        'callback_failed' => 'Оплата не прошла',
                        'callback_rejected' => 'Callback отклонён',
                        'mark_paid' => 'Отмечено оплаченным',
                        'cancel_payment' => 'Оплата отменена',
                        default => $state,
                    }),
                TextColumn::make('message')
                    ->label('Сообщение')
                    ->limit(50)
                    ->tooltip(fn ($record) => $record->message)
                    ->searchable(),
                TextColumn::make('order.number')
                    ->label('Заказ')
                    ->url(fn ($record) => $record->order_id ? OrderResource::getUrl('edit', ['record' => $record->order_id]) : null)
                    ->openUrlInNewTab()
                    ->placeholder('—'),
                TextColumn::make('meta.user_id')
                    ->label('Пользователь')
                    ->formatStateUsing(function ($state, $record) {
                        $userId = $record->meta['user_id'] ?? null;
                        if (!$userId) {
                            return '—';
                        }
                        $user = \App\Models\User::find($userId);
                        return $user ? $user->name : "ID: {$userId}";
                    })
                    ->url(fn ($record) => ($record->meta['user_id'] ?? null)
                        ? UserResource::getUrl('edit', ['record' => $record->meta['user_id']])
                        : null)
                    ->openUrlInNewTab()
                    ->placeholder('—'),
                TextColumn::make('level')
                    ->label('Уровень')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'error' => 'danger',
                        'warning' => 'warning',
                        default => 'info',
                    }),
                TextColumn::make('meta.amount')
                    ->label('Сумма')
                    ->formatStateUsing(fn ($state) => $state !== null ? number_format((float) $state, 2, '.', ' ') . ' ₽' : '—'),
                TextColumn::make('meta.contact_email')
                    ->label('Контакт'),
                TextColumn::make('meta.ip')
                    ->label('IP')
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('gateway')
                    ->label('Шлюз')
                    ->options([
                        'raiffeisen_acquiring' => 'Райффайзен',
                        'raiffeisen_ecom' => 'Райффайзен e-commerce',
                        'admin_manual' => 'Ручная операция',
                    ]),
                SelectFilter::make('channel')
                    ->label('Канал')
                    ->options([
                        'payment' => 'Оплата',
                    ]),
                SelectFilter::make('action')
                    ->label('Действие')
                    ->options([
                        'payment_initiated' => 'Платёж создан',
                        'order_created' => 'Заказ создан в API',
                        'order_create_failed' => 'Ошибка создания заказа в API',
                        'callback_received' => 'Получен callback',
                        'callback_success' => 'Оплата успешна',
                        'callback_failed' => 'Оплата не прошла',
                        'callback_rejected' => 'Callback отклонён',
                        'mark_paid' => 'Отмечено оплаченным',
                        'cancel_payment' => 'Оплата отменена',
                    ]),
                SelectFilter::make('level')
                    ->label('Уровень')
                    ->options([
                        'info' => 'Инфо',
                        'warning' => 'Предупреждение',
                        'error' => 'Ошибка',
                    ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([])
            ->bulkActions([])
            ->paginated([25, 50, 100]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGatewayLogs::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationLabel(): string
    {
        return 'Логи шлюзов';
    }
}
