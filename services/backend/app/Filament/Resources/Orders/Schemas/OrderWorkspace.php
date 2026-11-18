<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\Order\Order;
use App\Models\Order\OrderStatus;
use App\Rules\PhoneNumberRule;
use App\Services\Order\OperatorOrder;
use App\Support\PhoneNumber;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;

class OrderWorkspace
{
    /** Карточка заказа в две колонки: слева состав, получение и покупатель, справа состояние, оплата и суммы. */
    public static function components(): array
    {
        $operator = app(OperatorOrder::class);

        return [
            Grid::make(['default' => 1, 'lg' => 3])->schema([
                Group::make([
                    self::items(),
                    self::fulfillment($operator),
                    self::customer(),
                ])->columnSpan(['lg' => 2]),
                Group::make([
                    self::state($operator),
                    self::payment($operator),
                    self::totals(),
                    self::integration($operator),
                ])->columnSpan(['lg' => 1])->extraAttributes(['class' => 'order-summary']),
            ])->columnSpanFull(),
        ];
    }

    private static function items(): Section
    {
        return Section::make('Товары заказа')->schema([
            RepeatableEntry::make('items')->hiddenLabel()->table([
                TableColumn::make('Товар'), TableColumn::make('Артикул'), TableColumn::make('Количество'),
                TableColumn::make('Цена'), TableColumn::make('Сумма'),
            ])->schema([
                TextEntry::make('name')->hiddenLabel()->state(fn ($record) => $record->name ?: ($record->product?->name ?? 'Товар недоступен'))
                    ->url(fn ($record) => $record->product?->siteUrl(), shouldOpenInNewTab: true)->color('primary'),
                TextEntry::make('product.sku')->hiddenLabel()->placeholder('—'),
                TextEntry::make('quantity')->hiddenLabel(), TextEntry::make('price')->hiddenLabel()->money('RUB'),
                TextEntry::make('total')->hiddenLabel()->money('RUB'),
            ]),
        ]);
    }

    private static function fulfillment(OperatorOrder $operator): Section
    {
        return Section::make('Получение заказа')->description('Адрес и склад зафиксированы при оформлении. Для другого адреса нужен новый заказ.')->schema([
            TextEntry::make('delivery_type')->label('Способ получения')->formatStateUsing(fn ($state) => $state === 'pickup' ? 'Самовывоз' : 'Доставка'),
            TextEntry::make('warehouse_summary')->label('Склад / точка выдачи')->state(fn (Order $record) => $operator->warehouse($record)),
            TextEntry::make('fulfillment_term')->label(fn (Order $record) => $record->delivery_type === 'pickup' ? 'Срок готовности' : 'Срок доставки')
                ->state(fn (Order $record) => self::term($record))->placeholder('Не рассчитан'),
            TextEntry::make('saved_address')->label('Адрес')->state(fn (Order $record) => $operator->address($record))->columnSpanFull(),
            TextEntry::make('delivery_floor')->label('Этаж')->placeholder('Не указан'),
            TextEntry::make('deliveryHandlingType.name')->label('Подъём')->placeholder('Не выбран'),
            TextEntry::make('assembly_needed')->label('Сборка')->state(fn (Order $record) => $record->requires_assembly ? 'Нужна' : null)->placeholder('Не нужна'),
            TextEntry::make('delivery_date')->label('Согласованная дата')->date('d.m.Y')->placeholder('Не согласована'),
            TextEntry::make('delivery_time')->label('Время')->placeholder('Не согласовано'),
            TextEntry::make('service_names')->label('Дополнительные услуги')->state(fn (Order $record) => collect($record->fulfillment_snapshot['additional_services'] ?? [])->pluck('name')->implode(', ') ?: null)->placeholder('Не выбраны'),
        ])->columns(3)->extraAttributes(['class' => 'order-framed']);
    }

    private static function customer(): Section
    {
        return Section::make('Покупатель')->key('customer')->afterHeader([
            Action::make('edit_contacts')->label('Изменить')->icon('heroicon-o-pencil-square')->color('gray')->size('sm')
                ->visible(fn (Order $record) => OrderResource::canEdit($record))
                ->modalHeading('Данные покупателя')
                ->fillForm(fn (Order $record) => $record->only(['contact_name', 'contact_phone', 'contact_email', 'comment']))
                ->schema([
                    TextInput::make('contact_name')->label('Имя покупателя')->required()->maxLength(255),
                    TextInput::make('contact_phone')->label('Телефон')->required()
                        ->mutateStateForValidationUsing(fn (?string $state) => PhoneNumber::normalize($state))->rule(new PhoneNumberRule),
                    TextInput::make('contact_email')->label('Email')->email()->maxLength(255),
                    Textarea::make('comment')->label('Комментарий к заказу')->maxLength(1000)->columnSpanFull(),
                ])
                ->action(function (array $data, Order $record): void {
                    app(OperatorOrder::class)->updateContacts($record->id, $data);
                    $record->refresh();
                    Notification::make()->title('Контакты сохранены')->success()->send();
                }),
        ])->schema([
            TextEntry::make('contact_name')->label('Имя'),
            TextEntry::make('contact_phone')->label('Телефон')->color('primary')->formatStateUsing(fn (string $state) => PhoneNumber::format($state))
                ->url(fn (Order $record) => $record->contact_phone ? 'tel:'.PhoneNumber::normalize($record->contact_phone) : null),
            TextEntry::make('contact_email')->label('Email')->placeholder('Не указан')->color('primary')
                ->url(fn (Order $record) => $record->contact_email ? 'mailto:'.$record->contact_email : null),
            TextEntry::make('user.email')->placeholder('Без аккаунта')->color('primary')
                ->label(fn (Order $record) => $record->user && UserResource::canEdit($record->user)
                    ? new HtmlString('<a href="'.e(UserResource::getUrl('edit', ['record' => $record->user])).'" target="_blank" rel="noopener" style="color: var(--primary-600); text-decoration: underline">Аккаунт</a>')
                    : 'Аккаунт')
                ->url(fn (Order $record) => $record->user?->email ? 'mailto:'.$record->user->email : null),
            TextEntry::make('checkout_source')->label('Оформлен')
                ->state(fn (Order $record) => $record->guest_checkout ? 'Без авторизации' : 'Авторизованным пользователем'),
            TextEntry::make('other_orders')->label('Другие заказы')
                ->state(fn (Order $record) => $record->user_id ? (Order::where('user_id', $record->user_id)->whereKeyNot($record->id)->count() ?: null) : null)
                ->placeholder('Нет')->color('primary')
                ->url(fn (Order $record) => $record->user_id ? OrderResource::getUrl('index', ['filters' => ['user_id' => ['value' => $record->user_id]]]) : null),
            TextEntry::make('comment')->label('Комментарий покупателя')->placeholder('Нет комментария')
                ->formatStateUsing(fn (string $state) => nl2br(e($state)))->html()->columnSpanFull(),
        ])->columns(3)->extraAttributes(['class' => 'order-framed']);
    }

    /** Срок, показанный покупателю при оформлении, из снимка оформления. */
    private static function term(Order $order): ?string
    {
        $snapshot = $order->fulfillment_snapshot ?? [];
        $term = $snapshot[$order->delivery_type === 'pickup' ? 'ready' : 'delivery'] ?? null;
        if (! empty($snapshot['has_preorders']) || ! empty($snapshot['delivery_estimate_unavailable'])) {
            return $term['label'] ?? 'Срок поставки уточняется';
        }
        if (empty($term['date_min'])) {
            return null;
        }
        $from = Carbon::parse($term['date_min'])->format('d.m.Y');
        $to = Carbon::parse($term['date_max'] ?? $term['date_min'])->format('d.m.Y');
        $min = $term['days_min'] ?? null;
        $max = $term['days_max'] ?? $min;
        $days = $min === null ? '' : ' ('.($min === $max ? $min : $min.'–'.$max).' дн.)';

        return ($from === $to ? $from : $from.' – '.$to).$days;
    }

    private static function state(OperatorOrder $operator): Section
    {
        return Section::make('Сейчас по заказу')->schema([
            TextEntry::make('status')->label('Состояние заказа')->badge()
                ->formatStateUsing(fn ($state) => OrderStatus::tryFrom($state)?->label() ?? $state),
            TextEntry::make('operator_instruction')->label('Что делать')->state(fn (Order $record) => $operator->instruction($record)),
            TextEntry::make('cooling_until')->label('Можно отменить до')->dateTime('d.m.Y H:i')->placeholder('Период ожидания не задан'),
            TextEntry::make('cancellation_summary')->label('Обращение об отмене')
                ->state(fn (Order $record) => $record->cancellationRequest
                    ? match ($record->cancellationRequest->status) { 'new' => 'Новое обращение — требуется решение', 'review' => 'На рассмотрении', default => 'Рассмотрено' } : null)->placeholder('Нет обращения'),
            TextEntry::make('cancellationRequest.reason')->label('Причина покупателя')
                ->visible(fn (Order $record) => $record->cancellationRequest !== null),
            TextEntry::make('cancellationRequest.resolution')->label('Решение по обращению')
                ->visible(fn (Order $record) => filled($record->cancellationRequest?->resolution)),
            TextEntry::make('refund_summary')->label('Возврат денег')->state(fn (Order $record) => $record->refundTask
                ? ($record->refundTask->status === 'completed' ? 'Возврат подтверждён' : 'Требуется вернуть '.number_format((float) $record->refundTask->amount, 2, ',', ' ').' ₽') : null)->placeholder('Не требуется'),
        ])->inlineLabel();
    }

    private static function payment(OperatorOrder $operator): Section
    {
        return Section::make('Оплата')->schema([
            TextEntry::make('checkoutPaymentMethod.name')->label('Способ оплаты')->placeholder('Не указан'),
            TextEntry::make('payment_summary')->label('Состояние оплаты')->state(fn (Order $record) => $operator->paymentLabel($record))->badge(),
        ])->inlineLabel();
    }

    private static function totals(): Section
    {
        return Section::make('Стоимость')->schema([
            TextEntry::make('subtotal')->label('Товары')->money('RUB')->inlineLabel(),
            TextEntry::make('transport_summary')->label('Доставка')->state(fn (Order $record) => max(0, (float) $record->delivery_cost - (float) ($record->fulfillment_snapshot['handling_cost'] ?? 0)) ?: null)->money('RUB')->placeholder('0,00 ₽')->inlineLabel(),
            TextEntry::make('handling_summary')->label('Подъём и разгрузка')->state(fn (Order $record) => (float) ($record->fulfillment_snapshot['handling_cost'] ?? 0) ?: null)->money('RUB')->placeholder('0,00 ₽')->inlineLabel(),
            TextEntry::make('assembly_cost')->label('Сборка')->state(fn (Order $record) => (float) $record->assembly_cost ?: null)->money('RUB')->placeholder('0,00 ₽')->inlineLabel(),
            TextEntry::make('additional_summary')->label('Дополнительные услуги')->state(fn (Order $record) => (float) ($record->fulfillment_snapshot['additional_services_cost'] ?? 0) ?: null)->money('RUB')->placeholder('0,00 ₽')->inlineLabel(),
            TextEntry::make('total')->label('Итого к оплате')->money('RUB')->weight('bold')->size('lg')->inlineLabel(),
        ]);
    }

    private static function integration(OperatorOrder $operator): Section
    {
        return Section::make('Передача в 1С')->schema([
            TextEntry::make('integration_summary')->label('Состояние')->state(fn (Order $record) => $operator->integrationLabel($record)),
            TextEntry::make('integration_sent_at')->label('Передан')->dateTime('d.m.Y H:i')->placeholder('Ещё не передавался'),
        ])->inlineLabel();
    }
}
