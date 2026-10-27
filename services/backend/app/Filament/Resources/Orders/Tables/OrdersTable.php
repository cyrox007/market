<?php
namespace App\Filament\Resources\Orders\Tables;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order\OrderStatus;
use App\Services\Order\OperatorOrder;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        $operator = app(OperatorOrder::class);
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['payments', 'checkoutPaymentMethod', 'cancellationRequest', 'refundTask']))
            ->columns([
                TextColumn::make('number')->label('Заказ')->searchable()->sortable()->weight('bold')
                    ->description(fn ($record) => $record->created_at?->format('d.m.Y H:i')),
                TextColumn::make('contact_name')->label('Покупатель')->searchable(['contact_name', 'contact_phone', 'contact_email'])
                    ->description(fn ($record) => $record->contact_phone ?: 'Телефон не указан')->wrap(),
                TextColumn::make('total')->label('Сумма')->money('RUB')->sortable()->weight('bold'),
                TextColumn::make('payment_summary')->label('Оплата')->state(fn ($record) => $operator->paymentLabel($record))->badge()
                    ->color(fn ($state) => $state === 'Оплачено' ? 'success' : 'warning')
                    ->description(fn ($record) => $record->checkoutPaymentMethod?->name ?? 'Способ не указан'),
                TextColumn::make('status')->label('Состояние')->badge()->sortable()
                    ->formatStateUsing(fn ($state) => OrderStatus::tryFrom($state)?->label() ?? $state)
                    ->color(fn ($state) => match ($state) { 'manager_processing' => 'warning', 'assembling', 'assembled' => 'info', 'delivered' => 'success', 'cancelled' => 'danger', default => 'gray' })
                    ->description(fn ($record) => $record->status === 'manager_processing' && $record->cooling_until
                        ? (now()->lt($record->cooling_until) ? 'Можно отменить до '.$record->cooling_until->format('H:i') : 'Ожидает автоматической обработки') : null),
                TextColumn::make('fulfillment_summary')->label('Получение')->state(fn ($record) => $record->delivery_type === 'pickup' ? 'Самовывоз' : 'Доставка')
                    ->description(fn ($record) => $operator->warehouse($record))->tooltip(fn ($record) => $operator->address($record))->wrap(),
                TextColumn::make('attention_summary')->label('Требует внимания')->state(function ($record) {
                    $messages = [];
                    if ($record->integration_state === 'failed') { $messages[] = 'Ошибка передачи'; }
                    if ($record->cancellationRequest && $record->cancellationRequest->status !== 'resolved') { $messages[] = 'Просьба отменить'; }
                    if ($record->refundTask && $record->refundTask->status !== 'completed') { $messages[] = 'Возврат денег'; }
                    return implode(' · ', $messages) ?: 'Нет';
                })->wrap()->color(fn ($state) => $state === 'Нет' ? 'gray' : 'danger'),
                TextColumn::make('address_snapshot')->label('Адрес')->searchable()->toggleable(isToggledHiddenByDefault: true)->wrap(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Состояние заказа')->options(array_intersect_key(OrderStatus::options(), array_flip([
                    'manager_processing', 'assembling', 'assembled', 'shipped', 'in_transit', 'delivered', 'cancelled',
                ]))),
                SelectFilter::make('delivery_type')->label('Получение')->options(['delivery' => 'Доставка', 'pickup' => 'Самовывоз']),
                SelectFilter::make('payment_method')->label('Способ оплаты')->options(fn () => \App\Models\Payment\PaymentMethod::pluck('name', 'code')->all()),
                Filter::make('created_at')->label('Дата заказа')->schema([
                    DatePicker::make('from')->label('С даты'), DatePicker::make('until')->label('По дату'),
                ])->query(fn (Builder $query, array $data) => $query
                    ->when($data['from'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                    ->when($data['until'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '<=', $date))),
            ])
            ->recordUrl(fn ($record) => OrderResource::getUrl(OrderResource::canEdit($record) ? 'edit' : 'view', ['record' => $record]))
            ->recordActions([Action::make('open')->label('Открыть заказ')->url(fn ($record) => OrderResource::getUrl(OrderResource::canEdit($record) ? 'edit' : 'view', ['record' => $record]))])
            ->toolbarActions([])->defaultSort('created_at', 'desc')->defaultPaginationPageOption(25)->paginated([25, 50, 100]);
    }
}
