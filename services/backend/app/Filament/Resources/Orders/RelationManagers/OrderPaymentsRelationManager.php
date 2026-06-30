<?php

namespace App\Filament\Resources\Orders\RelationManagers;

use App\Contracts\Gateway\GatewayLoggerInterface;
use App\Support\Integration\OrderOneCSyncDispatcher;
use App\Models\Order\OrderStatus;
use App\Payment\Gateways\RaiffeisenEcomGateway;
use App\Services\Payment\RaiffeisenEcomClientFactory;
use App\Services\Payment\RaiffeisenEcomRefundService;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Vanilo\Payment\Models\PaymentStatusProxy;

class OrderPaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $title = 'Платежи';

    protected static ?string $recordTitleAttribute = 'hash';

    public function table(Table $table): Table
    {
        $paymentStatusLabel = fn ($record) => match (strtolower((string) $record->getStatus()->value())) {
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

        $paymentStatusColor = fn ($record) => match (strtolower((string) $record->getStatus()->value())) {
            'paid' => 'success',
            'refunded', 'partially_refunded' => 'gray',
            'cancelled', 'declined', 'timeout' => 'danger',
            'partially_paid', 'authorized', 'on_hold' => 'warning',
            default => 'gray',
        };

        $refundService = app(RaiffeisenEcomRefundService::class);

        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Дата')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                TextColumn::make('method.name')
                    ->label('Способ оплаты')
                    ->placeholder('—'),
                TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->formatStateUsing(fn ($record) => $paymentStatusLabel($record))
                    ->color(fn ($record) => $paymentStatusColor($record)),
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
            ->paginated([10, 25])
            ->headerActions([])
            ->recordActions([
                Action::make('mark_paid')
                    ->label('Отметить оплаченным')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->visible(fn ($record) => !$record->getStatus()->equals(PaymentStatusProxy::PAID()) && !$record->getStatus()->equals(PaymentStatusProxy::CANCELLED()))
                    ->requiresConfirmation()
                    ->modalHeading('Отметить платёж оплаченным')
                    ->modalDescription('Статус будет установлен «Оплачен», заказ при необходимости переведён в «Принят».')
                    ->action(function ($record): void {
                        $record->status = PaymentStatusProxy::PAID();
                        $record->amount_paid = (float) $record->getAmount();
                        $record->status_message = 'Оплата отмечена вручную в админке';
                        $record->save();
                        $order = $this->getOwnerRecord();
                        if (in_array($order->status, [OrderStatus::AWAITING_PAYMENT->value, OrderStatus::NEW->value], true)) {
                            $order->changeStatus(OrderStatus::ACCEPTED, 'Оплата отмечена вручную в админке', auth()->id());
                        }
                        OrderOneCSyncDispatcher::dispatch($order);
                        app(GatewayLoggerInterface::class)->log(
                            'admin_manual',
                            'mark_paid',
                            'Оплата отмечена вручную: заказ ' . $order->number . ', сумма ' . number_format((float) $order->total, 2, '.', ' ') . ' ₽',
                            [
                                'order_id' => $order->id,
                                'order_number' => $order->number,
                                'amount' => (float) $order->total,
                                'user_id' => auth()->id(),
                            ],
                            $record,
                            'payment',
                            'info'
                        );
                        Notification::make()->title('Платёж отмечен как оплаченный')->success()->send();
                    }),
                Action::make('cancel_payment')
                    ->label('Отменить')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn ($record) => !$record->getStatus()->equals(PaymentStatusProxy::PAID()) && !$record->getStatus()->equals(PaymentStatusProxy::CANCELLED()))
                    ->requiresConfirmation()
                    ->modalHeading('Отменить платёж')
                    ->modalDescription('Платёж будет отменён.')
                    ->action(function ($record): void {
                        $record->status = PaymentStatusProxy::CANCELLED();
                        $record->status_message = 'Оплата отменена вручную в админке';
                        $record->save();
                        $order = $this->getOwnerRecord();
                        app(GatewayLoggerInterface::class)->log(
                            'admin_manual',
                            'cancel_payment',
                            'Оплата отменена вручную: заказ ' . $order->number,
                            [
                                'order_id' => $order->id,
                                'order_number' => $order->number,
                                'amount' => (float) $record->getAmount(),
                                'user_id' => auth()->id(),
                            ],
                            $record,
                            'payment',
                            'info'
                        );
                        Notification::make()->title('Платёж отменён')->success()->send();
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
                            ->helperText('Введите сумму возврата. Максимум — оплаченная сумма минус уже возвращённое.'),
                        TextInput::make('comment')
                            ->label('Комментарий')
                            ->placeholder('Причина возврата')
                            ->maxLength(255),
                    ])
                    ->action(function (array $data, $record): void {
                        $refundService = app(RaiffeisenEcomRefundService::class);
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
                Action::make('check_transaction_status')
                    ->label('Проверить статус в банке')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->visible(fn ($record) => $record->getMethod()->getGateway() instanceof RaiffeisenEcomGateway)
                    ->action(function ($record): void {
                        $order = $this->getOwnerRecord();
                        $client = app(RaiffeisenEcomClientFactory::class)->createForPayment($record);
                        try {
                            $tx = $client->getOrderTransaction($order->number);
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
                            Notification::make()
                                ->title('Ошибка')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ]);
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Платежи');
    }
}
