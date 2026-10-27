<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Models\Order\Order;
use App\Models\Order\OrderStatus;
use App\Services\Order\OperatorOrder;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Schemas\Components\Section;

class OrderWorkspace
{
    public static function components(): array
    {
        $operator = app(OperatorOrder::class);
        return [
            Section::make('Сейчас по заказу')->schema([
                TextEntry::make('status')->label('Состояние заказа')->badge()
                    ->formatStateUsing(fn ($state) => OrderStatus::tryFrom($state)?->label() ?? $state),
                TextEntry::make('payment_summary')->label('Оплата')->state(fn (Order $record) => $operator->paymentLabel($record))->badge(),
                TextEntry::make('cooling_until')->label('Можно отменить до')->dateTime('d.m.Y H:i')->placeholder('Период ожидания не задан'),
                TextEntry::make('operator_instruction')->label('Что делать')->state(fn (Order $record) => $operator->instruction($record))->columnSpanFull(),
                TextEntry::make('integration_summary')->label('Передача в систему учёта')->state(fn (Order $record) => $operator->integrationLabel($record)),
                TextEntry::make('cancellation_summary')->label('Обращение об отмене')
                    ->state(fn (Order $record) => $record->cancellationRequest
                        ? match ($record->cancellationRequest->status) { 'new' => 'Новое обращение — требуется решение', 'review' => 'На рассмотрении', default => 'Рассмотрено' } : 'Нет обращения'),
                TextEntry::make('refund_summary')->label('Возврат денег')->state(fn (Order $record) => $record->refundTask
                    ? ($record->refundTask->status === 'completed' ? 'Возврат подтверждён' : 'Требуется вернуть '.number_format((float) $record->refundTask->amount, 2, ',', ' ').' ₽') : 'Не требуется'),
            ])->columns(3)->columnSpanFull(),
            Section::make('Товары заказа')->schema([
                RepeatableEntry::make('items')->hiddenLabel()->table([
                    TableColumn::make('Товар'), TableColumn::make('Артикул'), TableColumn::make('Количество'),
                    TableColumn::make('Цена'), TableColumn::make('Сумма'),
                ])->schema([
                    TextEntry::make('name')->hiddenLabel()->state(fn ($record) => $record->name ?: ($record->product?->name ?? 'Товар недоступен')),
                    TextEntry::make('product.sku')->hiddenLabel()->placeholder('—'),
                    TextEntry::make('quantity')->hiddenLabel(), TextEntry::make('price')->hiddenLabel()->money('RUB'),
                    TextEntry::make('total')->hiddenLabel()->money('RUB'),
                ]),
            ])->columnSpanFull(),
            Section::make('Получение заказа')->description('Адрес и склад зафиксированы при оформлении. Для другого адреса нужен новый заказ.')->schema([
                TextEntry::make('delivery_type')->label('Способ получения')->formatStateUsing(fn ($state) => $state === 'pickup' ? 'Самовывоз' : 'Доставка'),
                TextEntry::make('warehouse_summary')->label('Склад / точка выдачи')->state(fn (Order $record) => $operator->warehouse($record)),
                TextEntry::make('saved_address')->label('Адрес')->state(fn (Order $record) => $operator->address($record))->columnSpanFull(),
                TextEntry::make('delivery_floor')->label('Этаж')->placeholder('Не указан'),
                TextEntry::make('delivery_date')->label('Согласованная дата')->date('d.m.Y')->placeholder('Не согласована'),
                TextEntry::make('delivery_time')->label('Время')->placeholder('Не согласовано'),
            ])->columns(3)->columnSpanFull(),
            Section::make('Стоимость и услуги')->schema([
                TextEntry::make('subtotal')->label('Товары')->money('RUB'),
                TextEntry::make('transport_summary')->label('Доставка')->state(fn (Order $record) => max(0, (float) $record->delivery_cost - (float) ($record->fulfillment_snapshot['handling_cost'] ?? 0)))->money('RUB'),
                TextEntry::make('handling_summary')->label('Подъём и разгрузка')->state(fn (Order $record) => (float) ($record->fulfillment_snapshot['handling_cost'] ?? 0))->money('RUB'),
                TextEntry::make('assembly_cost')->label('Сборка')->money('RUB'),
                TextEntry::make('additional_summary')->label('Дополнительные услуги')->state(fn (Order $record) => (float) ($record->fulfillment_snapshot['additional_services_cost'] ?? 0))->money('RUB'),
                TextEntry::make('total')->label('Итого к оплате')->money('RUB')->weight('bold')->size('lg'),
                TextEntry::make('service_names')->label('Выбранные услуги')->state(fn (Order $record) => collect($record->fulfillment_snapshot['additional_services'] ?? [])->pluck('name')->implode(', ') ?: 'Дополнительные услуги не выбраны')->columnSpanFull(),
            ])->columns(3)->columnSpanFull(),
            Section::make('История заказа')->collapsed()->schema([
                RepeatableEntry::make('statusHistory')->hiddenLabel()->schema([
                    TextEntry::make('created_at')->label('Когда')->dateTime('d.m.Y H:i'),
                    TextEntry::make('status')->label('Событие')->formatStateUsing(fn ($state) => OrderStatus::tryFrom($state)?->label() ?? $state),
                    TextEntry::make('user.name')->label('Кто')->placeholder('Система'),
                    TextEntry::make('comment')->label('Комментарий')->columnSpanFull(),
                ])->columns(3),
            ])->columnSpanFull(),
        ];
    }
}
