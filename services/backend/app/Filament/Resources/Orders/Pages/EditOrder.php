<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Contracts\Gateway\GatewayLoggerInterface;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Orders\Schemas\OrderInfolist;
use App\Filament\Resources\Pages\EditRecord;
use App\Models\Order\OrderStatus;
use App\Services\Payment\RaiffeisenEcomRefundService;
use App\Support\Integration\OrderOneCSyncDispatcher;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Vanilo\Payment\Models\PaymentStatusProxy;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        $order = $this->record;
        $paymentModel = \Vanilo\Payment\Models\PaymentProxy::modelClass();
        $latestPayment = $paymentModel::where('payable_type', 'order')
            ->where('payable_id', $order->id)
            ->orderByRaw('CASE WHEN amount_paid > 0 THEN 0 ELSE 1 END')
            ->orderByDesc('id')
            ->first();

        $hasPaymentActions = $this->canMarkOrderPaid($order, $latestPayment)
            || $this->canCancelManualPayment($order, $latestPayment);

        $refundService = app(RaiffeisenEcomRefundService::class);
        $canRefund = $latestPayment && $refundService->canRefund($latestPayment);

        return [
            Action::make('refresh_order')->label('Обновить состояние')->icon('heroicon-o-arrow-path')
                ->action(fn () => $this->record->refresh()),
            Action::make('review_cancellation')->label('Рассмотреть просьбу об отмене')
                ->visible(fn () => $order->cancellationRequest && $order->cancellationRequest->status !== 'resolved')
                ->url(fn () => \App\Filament\Resources\CancellationRequests\CancellationRequestResource::getUrl('index')),
            Action::make('next_step')->label(fn () => app(\App\Services\Order\OperatorOrder::class)->next($order)['label'] ?? 'Следующее действие')
                ->visible(fn () => app(\App\Services\Order\OperatorOrder::class)->next($order) !== null)
                ->disabled(fn () => app(\App\Services\Order\OperatorOrder::class)->blockedReason($order) !== null)
                ->tooltip(fn () => app(\App\Services\Order\OperatorOrder::class)->blockedReason($order))
                ->color('primary')->requiresConfirmation()
                ->modalDescription('Подтвердите, что действие фактически выполнено. Изменение попадёт в историю заказа и систему учёта.')
                ->action(function () use ($order): void {
                    $next = app(\App\Services\Order\OperatorOrder::class)->next($order);
                    app(\App\Services\Order\OperatorOrder::class)->advance($order->id, $next['status'] ?? '', (int) auth()->id());
                    $this->record->refresh();
                    Notification::make()->title('Состояние заказа обновлено')->success()->send();
                }),
            Action::make('retry_integration')->label('Повторить передачу заказа')
                ->visible(fn () => $order->cooling_until && now()->gte($order->cooling_until)
                    && $order->status !== 'manager_processing' && $order->integration_state !== 'sent'
                    && ($order->status !== 'cancelled' || $order->integration_sent_at))
                ->requiresConfirmation()->action(function () use ($order): void {
                    OrderOneCSyncDispatcher::dispatch($order->id);
                    $this->record->refresh();
                    Notification::make()->title('Состояние передачи обновлено')->success()->send();
                }),
            Action::make('cancel_order')
                ->label('Отменить заказ')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn () => $order->canBeCancelled())
                ->requiresConfirmation()
                ->modalHeading('Отменить заказ')
                ->modalDescription('Заказ будет отменён, резерв товаров освободится. Если оплата получена, возврат денег проверяется отдельно и выполняется только через исходный платёж.')
                ->action(function () use ($order): void {
                    app(\App\Services\Order\OperatorOrder::class)->cancel($order->id, 'Заказ отменён оператором');
                    Notification::make()->title('Заказ отменён')->success()->send();
                    $this->redirect(OrderResource::getUrl('edit', ['record' => $order]));
                }),
            Action::make('force_cancel_order')
                ->label('Отмена по решению поддержки')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn () => ! $order->canBeCancelled() && ! in_array($order->status, ['cancelled', 'delivered'], true) && auth()->user()?->hasRole('super_admin'))
                ->requiresConfirmation()
                ->modalHeading('Решение об отмене заказа')
                ->modalDescription('Заказ в статусе «'.($order->getStatusLabel() ?? $order->status).'» будет отменён. Используйте только при необходимости.')
                ->schema([\Filament\Forms\Components\Textarea::make('reason')->label('Причина отмены')->required()->minLength(3)->maxLength(1000)])
                ->action(function (array $data) use ($order): void {
                    app(\App\Services\Order\OperatorOrder::class)->cancel($order->id, $data['reason'], true);
                    Notification::make()->title('Заказ отменён')->success()->send();
                    $this->redirect(OrderResource::getUrl('edit', ['record' => $order]));
                }),
            Action::make('print')
                ->label('Печать')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn () => route('admin.orders.print', $order->id))
                ->openUrlInNewTab(),
            ActionGroup::make([
                Action::make('mark_paid')
                    ->label('Подтвердить оплату при получении')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->visible(fn () => $this->canMarkOrderPaid($order, $latestPayment))
                    ->requiresConfirmation()
                    ->modalHeading('Отметить заказ оплаченным')
                    ->modalDescription('Подтвердите фактическое получение оплаты. Для банковских платежей статус устанавливает только банк. Период охлаждения сохраняется.')
                    ->action(function () use ($order, $latestPayment): void {
                        $this->markOrderAsPaid($order, $latestPayment);
                    }),
                Action::make('cancel_payment')
                    ->label('Отменить оплату')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn () => $this->canCancelManualPayment($order, $latestPayment))
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
                            ->maxValue(fn () => $refundService->getRefundableAmount($latestPayment))
                            ->helperText('Доступно: '.number_format($refundService->getRefundableAmount($latestPayment), 2).' ₽'),
                        TextInput::make('comment')
                            ->label('Комментарий')
                            ->placeholder('Причина возврата')
                            ->maxLength(255),
                    ])
                    ->action(function (array $data) use ($latestPayment, $refundService): void {
                        try {
                            $refund = $refundService->refund(
                                $latestPayment,
                                (float) $data['amount'],
                                $data['comment'] ?? null,
                                auth()->id()
                            );
                        } catch (\RuntimeException $error) {
                            report($error);
                            Notification::make()->title('Нужно проверить состояние возврата')
                                ->body('Операция не подтверждена. Проверьте историю оплаты перед повтором; банковский запрос мог быть принят.')->warning()->send();
                            return;
                        }
                        Notification::make()->title(match ($refund->status) {
                            'completed' => 'Банк подтвердил возврат', 'failed' => 'Возврат отклонён — требуется проверка',
                            default => 'Запрос возврата принят, ожидается подтверждение банка',
                        })->color(match ($refund->status) { 'completed' => 'success', 'failed' => 'danger', default => 'warning' })->send();
                        $this->record->refresh();
                    }),
            ])
                ->label('Оплата')
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->visible($hasPaymentActions || $canRefund)
                ->dropdownPlacement('bottom-end'),
        ];
    }

    protected function canCancelManualPayment($order, $payment): bool
    {
        return app(\App\Services\Order\ManualOrderPayment::class)->allowed($order)
            && $payment && (float) $payment->amount_paid <= 0
            && ! in_array((string) $payment->getRawOriginal('status'), ['paid', 'partially_refunded', 'refunded', 'cancelled'], true);
    }

    protected function canMarkOrderPaid($order, $latestPayment): bool
    {
        if (! app(\App\Services\Order\ManualOrderPayment::class)->allowed($order)) { return false; }
        if ($latestPayment && ((float) $latestPayment->amount_paid > 0
            || in_array($latestPayment->getRawOriginal('status'), ['paid', 'refunded', 'partially_refunded', 'cancelled'], true))) { return false; }
        if ($order->cooling_until) {
            return ! in_array($order->status, [OrderStatus::CANCELLED->value, OrderStatus::DELIVERED->value], true)
                && ! app(\App\Services\Order\OrderCooling::class)->isPaid($order);
        }
        if (in_array($order->status, [OrderStatus::AWAITING_PAYMENT->value, OrderStatus::NEW->value], true)) {
            return true;
        }

        return $latestPayment && ! $latestPayment->getStatus()->equals(PaymentStatusProxy::PAID());
    }

    protected function markOrderAsPaid($order, $latestPayment): void
    {
        $latestPayment = app(\App\Services\Order\ManualOrderPayment::class)->record($order->id, $latestPayment?->id, true);

        if (in_array($order->status, [OrderStatus::AWAITING_PAYMENT->value, OrderStatus::NEW->value], true)) {
            $order->changeStatus(OrderStatus::ACCEPTED, 'Оплата отмечена вручную в админке', auth()->id());
        }
        OrderOneCSyncDispatcher::dispatch($order);

        app(GatewayLoggerInterface::class)->log(
            'admin_manual',
            'mark_paid',
            'Оплата отмечена вручную: заказ '.$order->number.', сумма '.number_format((float) $order->total, 2, '.', ' ').' ₽',
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
        $this->refreshFormData(['status']);
    }

    protected function cancelOrderPayment($order, $payment): void
    {
        $payment = app(\App\Services\Order\ManualOrderPayment::class)->record($order->id, $payment->id, false);

        app(GatewayLoggerInterface::class)->log(
            'admin_manual',
            'cancel_payment',
            'Оплата отменена вручную: заказ '.$order->number,
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
    }

    public function infolist(Schema $schema): Schema
    {
        return OrderInfolist::configure($schema);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $this->record->load(['items.product', 'payments', 'checkoutPaymentMethod', 'statusHistory.user', 'cancellationRequest', 'refundTask']);
        return $data;
    }

    protected function handleRecordUpdate($record, array $data): \Illuminate\Database\Eloquent\Model
    {
        $record->update(\Illuminate\Support\Arr::only($data, ['contact_name', 'contact_phone', 'contact_email', 'comment']));
        return $record;
    }

    protected function getFormActions(): array
    {
        return [$this->getSaveFormAction()->label('Сохранить контакты и заметку'), $this->getCancelFormAction()->label('К списку заказов')];
    }

    protected function getRedirectUrl(): ?string { return null; }

    public function getTitle(): string
    {
        return 'Заказ '.$this->record->number;
    }
    public static function getNavigationLabel(): string
    {
        return __('filament/admin_sv/edit_order.title');
    }
}
