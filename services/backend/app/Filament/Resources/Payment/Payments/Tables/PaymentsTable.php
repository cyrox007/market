<?php

namespace App\Filament\Resources\Payment\Payments\Tables;

use App\Payment\Gateways\RaiffeisenEcomGateway;
use App\Services\Payment\RaiffeisenEcomClientFactory;
use App\Services\Payment\RaiffeisenEcomRefundService;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use App\Filament\Resources\Orders\OrderResource;
use Vanilo\Payment\Models\PaymentStatusProxy;

class PaymentsTable
{
    public static function configure(Table $table): Table
    {
        $refundService = app(RaiffeisenEcomRefundService::class);

        $statusLabel = fn ($record) => match (strtolower((string) $record->getStatus()->value())) {
            'pending' => 'Ожидает',
            'authorized' => 'Авторизован',
            'on_hold' => 'На удержании',
            'paid' => 'Оплачен',
            'partially_paid' => 'Частично оплачен',
            'declined' => 'Отклонён',
            'timeout' => 'Истёк',
            'cancelled' => 'Отменён',
            'refunded' => 'Возвращён',
            'partially_refunded' => 'Частично возвращён',
            default => (string) $record->getStatus()->value(),
        };

        $statusColor = fn ($record) => match (strtolower((string) $record->getStatus()->value())) {
            'paid' => 'success',
            'refunded', 'partially_refunded' => 'gray',
            'cancelled', 'declined', 'timeout' => 'danger',
            'partially_paid', 'authorized', 'on_hold' => 'warning',
            default => 'gray',
        };

        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['payable', 'method']))
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Дата')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                TextColumn::make('payable_id')
                    ->label('Заказ')
                    ->formatStateUsing(fn ($record) => $record->payable?->number ?? '—')
                    ->url(fn ($record) => $record->payable_type === 'order' && $record->payable_id
                        ? OrderResource::getUrl('edit', ['record' => $record->payable_id])
                        : null)
                    ->openUrlInNewTab()
                    ->sortable(),
                TextColumn::make('method.name')
                    ->label('Способ оплаты')
                    ->placeholder('—'),
                TextColumn::make('method.gateway')
                    ->label('Шлюз')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'raiffeisen_ecom' => 'Raif e-commerce',
                        'raiffeisen_acquiring' => 'Raif acquiring',
                        'manual' => 'Ручной',
                        default => $state ?? '—',
                    })
                    ->badge(),
                TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->formatStateUsing(fn ($record) => $statusLabel($record))
                    ->color(fn ($record) => $statusColor($record)),
                TextColumn::make('amount')
                    ->label('Сумма')
                    ->money('RUB')
                    ->sortable(),
                TextColumn::make('amount_paid')
                    ->label('Оплачено')
                    ->money('RUB')
                    ->sortable(),
                TextColumn::make('remote_id')
                    ->label('ID транзакции')
                    ->placeholder('—')
                    ->copyable()
                    ->limit(24),
                TextColumn::make('status_message')
                    ->label('Сообщение')
                    ->limit(40)
                    ->placeholder('—')
                    ->tooltip(fn ($record) => $record->status_message),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Статус')
                    ->options([
                        'pending' => 'Ожидает',
                        'paid' => 'Оплачен',
                        'cancelled' => 'Отменён',
                        'refunded' => 'Возвращён',
                        'declined' => 'Отклонён',
                    ]),
                SelectFilter::make('gateway')
                    ->label('Шлюз')
                    ->options([
                        'raiffeisen_ecom' => 'Raif e-commerce',
                        'raiffeisen_acquiring' => 'Raif acquiring',
                        'manual' => 'Ручной',
                    ])
                    ->query(function ($query, array $data): void {
                        $value = $data['gateway'] ?? $data['value'] ?? null;
                        if (!empty($value)) {
                            $query->whereHas('method', fn ($q) => $q->where('gateway', $value));
                        }
                    }),
            ])
            ->paginated([25, 50, 100])
            ->recordActions([
                Action::make('cancel_in_bank')
                    ->label('Отменить в банке')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn ($record) => $refundService->canCancelInBank($record))
                    ->requiresConfirmation()
                    ->modalHeading('Отменить платёж в API банка')
                    ->modalDescription('Заказ будет отменён в Raiffeisen API. Платёж получит статус «Отменён».')
                    ->action(function ($record) use ($refundService): void {
                        $refundService->cancelInBank($record, auth()->id());
                        Notification::make()->title('Платёж отменён в банке')->success()->send();
                    }),
                Action::make('refund')
                    ->label('Оформить возврат')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->visible(fn ($record) => $refundService->canRefund($record))
                    ->fillForm(fn ($record) => [
                        'amount' => $refundService->getRefundableAmount($record),
                    ])
                    ->form([
                        TextInput::make('amount')
                            ->label('Сумма возврата (₽)')
                            ->numeric()
                            ->required()
                            ->minValue(0.01)
                            ->helperText(fn ($record) => 'Доступно: ' . number_format($refundService->getRefundableAmount($record), 2) . ' ₽'),
                        TextInput::make('comment')
                            ->label('Комментарий')
                            ->placeholder('Причина возврата')
                            ->maxLength(255),
                    ])
                    ->action(function (array $data, $record) use ($refundService): void {
                        $refundService->refund(
                            $record,
                            (float) $data['amount'],
                            $data['comment'] ?? null,
                            auth()->id()
                        );
                        Notification::make()
                            ->title('Возврат оформлен')
                            ->body('Сумма ' . number_format((float) $data['amount'], 2) . ' ₽ отправлена в банк.')
                            ->success()
                            ->send();
                    }),
                Action::make('check_status')
                    ->label('Проверить статус в банке')
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn ($record) => $record->getMethod()->getGateway() instanceof RaiffeisenEcomGateway)
                    ->action(function ($record): void {
                        $order = $record->getPayable();
                        if (!$order || !method_exists($order, 'getNumber')) {
                            Notification::make()->title('Заказ не найден')->danger()->send();
                            return;
                        }
                        $client = app(RaiffeisenEcomClientFactory::class)->createForPayment($record);
                        try {
                            $tx = $client->getOrderTransaction($order->getNumber());
                            if (!empty($tx['_not_found'])) {
                                Notification::make()
                                    ->title('Заказ не найден в Raif API')
                                    ->body($tx['message'] ?? 'Заказ истёк, уже отменён или был создан через GET /pay без orderId.')
                                    ->warning()
                                    ->send();
                                return;
                            }
                            $status = $tx['transaction']['status']['value'] ?? $tx['status']['value'] ?? 'unknown';
                            $amount = $tx['transaction']['amount'] ?? $tx['amount'] ?? null;
                            Notification::make()
                                ->title('Статус в банке')
                                ->body('Статус: ' . $status . ($amount ? ', сумма: ' . number_format((float) $amount, 2) . ' ₽' : ''))
                                ->info()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('Ошибка')->body($e->getMessage())->danger()->send();
                        }
                    }),
            ]);
    }
}
