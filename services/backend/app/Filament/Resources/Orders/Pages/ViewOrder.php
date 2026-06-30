<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Contracts\Gateway\GatewayLoggerInterface;
use App\Support\Integration\OrderOneCSyncDispatcher;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order\OrderStatus;
use App\Payment\Gateways\SberbankAcquiringGateway;
use App\Payment\Gateways\RaiffeisenEcomGateway;
use App\Services\Payment\RaiffeisenEcomRefundService;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Vanilo\Payment\Models\PaymentStatusProxy;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        $order = $this->record;
        $paymentModel = \Vanilo\Payment\Models\PaymentProxy::modelClass();
        $latestPayment = $paymentModel::where('payable_type', 'order')
            ->where('payable_id', $order->id)
            ->orderByDesc('id')
            ->first();

        $hasPaymentActions = $this->canMarkOrderPaid($order, $latestPayment)
            || ($latestPayment && !$latestPayment->getStatus()->equals(PaymentStatusProxy::PAID()) && !$latestPayment->getStatus()->equals(PaymentStatusProxy::CANCELLED()));

        $refundService = app(RaiffeisenEcomRefundService::class);
        $canRefund = $latestPayment && $refundService->canRefund($latestPayment);

        return [
            Action::make('cancel_order')
                ->label('Отменить заказ')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn () => $order->canBeCancelled())
                ->requiresConfirmation()
                ->modalHeading('Отменить заказ')
                ->modalDescription('Заказ будет отменён. Это действие нельзя отменить.')
                ->action(function () use ($order): void {
                    $order->changeStatus(OrderStatus::CANCELLED, 'Заказ отменён администратором', auth()->id());
                    Notification::make()->title('Заказ отменён')->success()->send();
                    $this->redirect(OrderResource::getUrl('view', ['record' => $order]));
                }),
            Action::make('force_cancel_order')
                ->label('Принудительно отменить')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn () => !$order->canBeCancelled() && $order->status !== OrderStatus::CANCELLED->value && auth()->user()?->hasRole('super_admin'))
                ->requiresConfirmation()
                ->modalHeading('Принудительно отменить заказ')
                ->modalDescription('Заказ в статусе «' . $order->getStatusLabel() . '» будет отменён. Используйте только при необходимости.')
                ->action(function () use ($order): void {
                    $order->changeStatus(OrderStatus::CANCELLED, 'Заказ принудительно отменён суперадмином', auth()->id());
                    Notification::make()->title('Заказ отменён')->success()->send();
                    $this->redirect(OrderResource::getUrl('view', ['record' => $order]));
                }),
            Action::make('print')
                ->label('Печать')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn () => route('admin.orders.print', $order->id))
                ->openUrlInNewTab(),
            ActionGroup::make([
                Action::make('open_payment')
                    ->label('Открыть оплату в банке')
                    ->icon('heroicon-o-credit-card')
                    ->color('primary')
                    ->visible(fn () => $order->canPayOnline())
                    ->url(fn () => $this->resolvePaymentFormUrl($order, $latestPayment))
                    ->openUrlInNewTab(),
                Action::make('mark_paid')
                    ->label('Отметить оплаченным')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->visible(fn () => $this->canMarkOrderPaid($order, $latestPayment))
                    ->requiresConfirmation()
                    ->modalHeading('Отметить заказ оплаченным')
                    ->modalDescription('Статус платежа будет установлен «Оплачен», при необходимости заказ переведён в статус «Принят». Продолжить?')
                    ->action(function () use ($order, $latestPayment): void {
                        $this->markOrderAsPaid($order, $latestPayment);
                    }),
                Action::make('cancel_payment')
                    ->label('Отменить оплату')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn () => $latestPayment && !$latestPayment->getStatus()->equals(PaymentStatusProxy::PAID()) && !$latestPayment->getStatus()->equals(PaymentStatusProxy::CANCELLED()))
                    ->requiresConfirmation()
                    ->modalHeading('Отменить оплату')
                    ->modalDescription('Платёж по заказу будет отменён. Продолжить?')
                    ->action(function () use ($order, $latestPayment): void {
                        $this->cancelOrderPayment($order, $latestPayment);
                    }),
                Action::make('refund')
                    ->label('Оформить возврат')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->visible(fn () => $canRefund)
                    ->fillForm(fn () => [
                        'amount' => $refundService->getRefundableAmount($latestPayment),
                    ])
                    ->form([
                        TextInput::make('amount')
                            ->label('Сумма возврата (₽)')
                            ->numeric()
                            ->required()
                            ->minValue(0.01)
                            ->helperText('Доступно: ' . number_format($refundService->getRefundableAmount($latestPayment), 2) . ' ₽'),
                        TextInput::make('comment')
                            ->label('Комментарий')
                            ->placeholder('Причина возврата')
                            ->maxLength(255),
                    ])
                    ->action(function (array $data) use ($order, $latestPayment, $refundService): void {
                        $refundService->refund(
                            $latestPayment,
                            (float) $data['amount'],
                            $data['comment'] ?? null,
                            auth()->id()
                        );
                        Notification::make()
                            ->title('Возврат оформлен')
                            ->body('Сумма ' . number_format((float) $data['amount'], 2) . ' ₽ отправлена в банк.')
                            ->success()
                            ->send();
                        $this->record->refresh();
                    }),
            ])
                ->label('Оплата')
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->visible($hasPaymentActions || $canRefund)
                ->dropdownPlacement('bottom-end'),
            Actions\EditAction::make(),
        ];
    }

    protected function canMarkOrderPaid($order, $latestPayment): bool
    {
        if (in_array($order->status, [OrderStatus::AWAITING_PAYMENT->value, OrderStatus::NEW->value], true)) {
            return true;
        }
        return $latestPayment && !$latestPayment->getStatus()->equals(PaymentStatusProxy::PAID());
    }

    protected function markOrderAsPaid($order, $latestPayment): void
    {
        if ($latestPayment && !$latestPayment->getStatus()->equals(PaymentStatusProxy::PAID())) {
            $latestPayment->status = PaymentStatusProxy::PAID();
            $latestPayment->amount_paid = (float) $latestPayment->getAmount();
            $latestPayment->status_message = 'Оплата отмечена вручную в админке';
            $latestPayment->save();
        }

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
            $latestPayment,
            'payment',
            'info'
        );

        Notification::make()
            ->title('Заказ отмечен как оплаченный')
            ->success()
            ->send();
        $this->record->refresh();
    }

    protected function cancelOrderPayment($order, $payment): void
    {
        $payment->status = PaymentStatusProxy::CANCELLED();
        $payment->status_message = 'Оплата отменена вручную в админке';
        $payment->save();

        app(GatewayLoggerInterface::class)->log(
            'admin_manual',
            'cancel_payment',
            'Оплата отменена вручную: заказ ' . $order->number,
            [
                'order_id' => $order->id,
                'order_number' => $order->number,
                'amount' => (float) $payment->getAmount(),
                'user_id' => auth()->id(),
            ],
            $payment,
            'payment',
            'info'
        );

        Notification::make()
            ->title('Оплата по заказу отменена')
            ->success()
            ->send();
        $this->record->refresh();
    }

    protected function resolvePaymentFormUrl($order, $latestPayment): ?string
    {
        $cached = $order->getCachedPayformUrl();
        if ($cached) {
            return $cached;
        }

        if (!$latestPayment) {
            return null;
        }

        $method = $latestPayment->getMethod();
        if (!$method) {
            return null;
        }

        $gateway = $method->getGateway();
        $config = null;
        if ($gateway instanceof SberbankAcquiringGateway) {
            $config = $gateway->getClientConfig($method, $order);
        } elseif ($gateway instanceof RaiffeisenEcomGateway) {
            $config = $gateway->getClientConfig($method, $order);
        }

        return is_array($config) ? ($config['payformUrl'] ?? null) : null;
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Загружаем связи для отображения в форме
        $this->record->load([
            'user',
            'shippingLocation.parent',
            'region',
            'shippingMethod.carrier',
            'deliveryHandlingType',
            'address',
            'items.product',
            'statusHistory.user',
            'additionalServices',
        ]);

        return $data;
    }
    public function getTitle(): string
    {
        return __('filament/admin_sv/view_order.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament/admin_sv/view_order.title');
    }

}
