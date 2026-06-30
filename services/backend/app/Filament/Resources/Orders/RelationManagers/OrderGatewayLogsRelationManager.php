<?php

namespace App\Filament\Resources\Orders\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class OrderGatewayLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'gatewayLogs';

    protected static ?string $title = 'Логи шлюзов (оплата и др.)';

    protected static ?string $recordTitleAttribute = 'id';

    public function table(Table $table): Table
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
                        default => $state,
                    }),
                TextColumn::make('action')
                    ->label('Действие')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'payment_initiated' => 'Платёж создан',
                        'callback_received' => 'Получен callback',
                        'callback_success' => 'Оплата успешна',
                        'callback_failed' => 'Оплата не прошла',
                        'callback_rejected' => 'Callback отклонён',
                        default => $state,
                    }),
                TextColumn::make('message')
                    ->label('Сообщение')
                    ->limit(60)
                    ->tooltip(fn ($record) => $record->message),
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
                    ->label('Email'),
                TextColumn::make('meta.ip')
                    ->label('IP')
                    ->placeholder('—'),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50]);
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Логи шлюзов (оплата и др.)');
    }
}
