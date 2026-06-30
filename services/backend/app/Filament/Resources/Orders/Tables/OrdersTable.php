<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Models\Order\OrderStatus;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function ($query) {
                $query->with([
                    'user',
                    'address',
                    'shippingLocation',
                    'region',
                    'shippingMethod.carrier',
                    'deliveryHandlingType',
                    'additionalServices'
                ]);
            })
            ->columns([
                TextColumn::make('number')
                    ->label(__('filament/admin_sv/order_resource.number'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('status')
                    ->label(__('filament/admin_sv/order_resource.status'))
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'new' => 'info',
                        'awaiting_payment' => 'warning',
                        'accepted' => 'warning',
                        'assembled' => 'warning',
                        'shipped' => 'info',
                        'in_transit' => 'info',
                        'delivered' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'new' => 'Новый',
                        'awaiting_payment' => 'Ожидание оплаты',
                        'accepted' => 'Принят',
                        'assembled' => 'Собран',
                        'shipped' => 'Отправлен',
                        'in_transit' => 'В пути',
                        'delivered' => 'Доставлен',
                        'cancelled' => 'Отменен',
                        default => $state,
                    })
                    ->sortable(),
                TextColumn::make('contact_name')
                    ->label(__('filament/admin_sv/order_resource.contact_name'))
                    ->description(fn($record) => $record ? ($record->contact_phone . ' / ' . $record->contact_email) : '')
                    ->searchable(['contact_name', 'contact_phone', 'contact_email'])
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label(__('filament/admin_sv/order_resource.user.name'))
                    ->searchable()
                    ->sortable()
                    ->default('Гостевой заказ')
                    ->placeholder('Гостевой заказ'),
                TextColumn::make('delivery_type')
                    ->label(__('filament/admin_sv/order_resource.delivery_type'))
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'delivery' => 'success',
                        'pickup' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'delivery' => 'Доставка',
                        'pickup' => 'Самовывоз',
                        default => $state,
                    })
                    ->sortable(),
                TextColumn::make('shipping_location.name')
                    ->label(__('filament/admin_sv/order_resource.shipping_location.name'))
                    ->searchable()
                    ->sortable()
                    ->placeholder('—')
                    ->default('—'),
                TextColumn::make('address_snapshot')
                    ->label(__('filament/admin_sv/order_resource.address.full_address'))
                    ->state(fn ($record) => $record->address_snapshot ?: $record->address?->full_address)
                    ->searchable(['address_snapshot'])
                    ->limit(30)
                    ->tooltip(fn($record) => $record->address_snapshot ?: $record?->address?->full_address)
                    ->placeholder('—')
                    ->default('—'),
                TextColumn::make('shipping_method.name')
                    ->label(__('filament/admin_sv/order_resource.shipping_method.name'))
                    ->description(fn($record) => $record?->shippingMethod?->carrier?->name)
                    ->placeholder('—')
                    ->visible(fn($record) => $record && $record->shipping_method_id !== null),
                TextColumn::make('subtotal')
                    ->label(__('filament/admin_sv/order_resource.subtotal'))
                    ->money('RUB')
                    ->sortable(),
                TextColumn::make('delivery_cost')
                    ->label(__('filament/admin_sv/order_resource.delivery_cost'))
                    ->money('RUB')
                    ->sortable()
                    ->default(0)
                    ->visible(fn($record) => $record && ($record->delivery_type === 'delivery' || $record->delivery_cost > 0)),
                TextColumn::make('assembly_cost')
                    ->label(__('filament/admin_sv/order_resource.assembly_cost'))
                    ->money('RUB')
                    ->sortable()
                    ->default(0)
                    ->visible(fn($record) => $record && ($record->requires_assembly || $record->assembly_cost > 0)),
                TextColumn::make('additional_services_count')
                    ->label('Доп. услуги')
                    ->counts('additionalServices')
                    ->badge()
                    ->color('info')
                    ->default(0)
                    ->visible(fn($record) => $record && $record->additionalServices && $record->additionalServices->count() > 0),
                TextColumn::make('total')
                    ->label(__('filament/admin_sv/order_resource.total'))
                    ->money('RUB')
                    ->sortable()
                    ->weight('bold')
                    ->color('success'),
                TextColumn::make('payment_method')
                    ->label(__('filament/admin_sv/order_resource.payment_method'))
                    ->badge()
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'card' => 'Карта',
                        'card_in_store' => 'Карта (онлайн)',
                        'card_ecom' => 'Карта (онлайн e-commerce)',
                        'cash' => 'Наличные',
                        'installment' => 'Рассрочка',
                        default => $state,
                    })
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('filament/admin_sv/order_resource.created_at'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Статус')
                    ->options([
                        'new' => 'Новый',
                        'awaiting_payment' => 'Ожидание оплаты',
                        'accepted' => 'Принят',
                        'assembled' => 'Собран',
                        'shipped' => 'Отправлен',
                        'in_transit' => 'В пути',
                        'delivered' => 'Доставлен',
                        'cancelled' => 'Отменен',
                    ]),
                SelectFilter::make('delivery_type')
                    ->label('Тип доставки')
                    ->options([
                        'delivery' => 'Доставка',
                        'pickup' => 'Самовывоз',
                    ]),
                SelectFilter::make('payment_method')
                    ->label('Способ оплаты')
                    ->options([
                        'card' => 'Карта',
                        'card_in_store' => 'Карта (онлайн)',
                        'card_ecom' => 'Карта (онлайн e-commerce)',
                        'cash' => 'Наличные',
                        'installment' => 'Рассрочка',
                    ]),
                Filter::make('created_at')
                    ->label('Период создания')
                    ->form([
                        DatePicker::make('created_from')
                            ->label('Дата с'),
                        DatePicker::make('created_until')
                            ->label('Дата по'),
                    ])
                    ->query(function (Builder $query, array $data): void {
                        if (!empty($data['created_from'])) {
                            $query->whereDate('created_at', '>=', $data['created_from']);
                        }
                        if (!empty($data['created_until'])) {
                            $query->whereDate('created_at', '<=', $data['created_until']);
                        }
                    })
                    ->indicateUsing(function (array $data): array {
                        if (empty($data['created_from']) && empty($data['created_until'])) {
                            return [];
                        }
                        $parts = [];
                        if (!empty($data['created_from'])) {
                            $parts[] = 'с ' . \Carbon\Carbon::parse($data['created_from'])->format('d.m.Y');
                        }
                        if (!empty($data['created_until'])) {
                            $parts[] = 'по ' . \Carbon\Carbon::parse($data['created_until'])->format('d.m.Y');
                        }
                        return [Indicator::make('Период: ' . implode(', ', $parts))];
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->defaultPaginationPageOption(25)
            ->paginated([10, 25, 50, 100])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('cancel_order')
                    ->label('Отменить')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn ($record) => $record && $record->canBeCancelled())
                    ->requiresConfirmation()
                    ->modalHeading('Отменить заказ')
                    ->modalDescription(fn ($record) => 'Заказ ' . ($record?->number ?? '') . ' будет отменён.')
                    ->action(function ($record): void {
                        $record->changeStatus(OrderStatus::CANCELLED, 'Заказ отменён администратором', auth()->id());
                        Notification::make()->title('Заказ отменён')->success()->send();
                    }),
                Action::make('force_cancel_order')
                    ->label('Принудительно отменить')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn ($record) => $record && !$record->canBeCancelled() && $record->status !== OrderStatus::CANCELLED->value && auth()->user()?->hasRole('super_admin'))
                    ->requiresConfirmation()
                    ->modalHeading('Принудительно отменить заказ')
                    ->modalDescription(fn ($record) => 'Заказ ' . ($record?->number ?? '') . ' будет отменён даже при нестандартном статусе.')
                    ->action(function ($record): void {
                        $record->changeStatus(OrderStatus::CANCELLED, 'Заказ принудительно отменён суперадмином', auth()->id());
                        Notification::make()->title('Заказ отменён')->success()->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('change_status')
                        ->label('Изменить статус')
                        ->icon('heroicon-o-arrow-path')
                        ->form([
                            Select::make('status')
                                ->label('Новый статус')
                                ->options(collect(OrderStatus::cases())->mapWithKeys(fn (OrderStatus $s) => [$s->value => $s->label()]))
                                ->required(),
                        ])
                        ->action(function ($records, array $data): void {
                            $status = OrderStatus::from($data['status']);
                            $count = 0;
                            foreach ($records as $order) {
                                if ($order->status !== $status->value) {
                                    $order->changeStatus($status, 'Массовое изменение в админке', auth()->id());
                                    $count++;
                                }
                            }
                            Notification::make()
                                ->title("Статус обновлён у заказов: {$count}")
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
