<?php

namespace App\Filament\Resources\Orders\RelationManagers;

use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use App\Models\Shipping\AdditionalService;

class OrderAdditionalServicesRelationManager extends RelationManager
{
    protected static string $relationship = 'additionalServices';

    protected static ?string $title = 'Дополнительные услуги';

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Убеждаемся, что услуги загружены
        $this->getOwnerRecord()->load('additionalServices');
        return $data;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('pivot.service_name')
                    ->label('Название')
                    ->formatStateUsing(function ($state, $record) {
                        return $state ?: ($record->name ?? '—');
                    })
                    ->searchable(),

                TextColumn::make('pivot.icon')
                    ->label('Иконка')
                    ->formatStateUsing(function ($state, $record) {
                        $icon = $state ?: ($record->icon ?? null);
                        return $icon ? "<i class='{$icon} text-xl text-red-600'></i>" : '—';
                    })
                    ->html(),

                TextColumn::make('pivot.price_type')
                    ->label('Тип цены')
                    ->formatStateUsing(function ($state, $record) {
                        $priceType = $state ?: ($record->price_type ?? 'fixed');
                        return match($priceType) {
                            'fixed' => 'Фиксированная',
                            'from' => 'От X',
                            'custom' => 'Отдельно',
                            default => $priceType,
                        };
                    })
                    ->badge()
                    ->color(function ($state, $record) {
                        $priceType = $state ?: ($record->price_type ?? 'fixed');
                        return match($priceType) {
                            'fixed' => 'success',
                            'from' => 'warning',
                            'custom' => 'info',
                            default => 'gray',
                        };
                    }),

                TextColumn::make('pivot.price')
                    ->label('Цена')
                    ->formatStateUsing(function ($state, $record) {
                        $price = $state !== null ? (float) $state : 0;
                        $priceType = $record->pivot->price_type ?? $record->price_type ?? 'fixed';
                        
                        if ($priceType === 'custom') {
                            return 'По договоренности';
                        }
                        
                        if ($priceType === 'from') {
                            return 'от ' . number_format($price, 2, '.', ' ') . ' ₽';
                        }
                        
                        return number_format($price, 2, '.', ' ') . ' ₽';
                    })
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                AttachAction::make()
                    ->preloadRecordSelect()
                    ->recordSelect(
                        fn (Select $select) => $select
                            ->label('Услуга')
                            ->options(function () {
                                $order = $this->getOwnerRecord();
                                // Получаем ID уже добавленных услуг
                                $attachedServiceIds = $order->additionalServices()->pluck('additional_services.id')->toArray();
                                
                                // Получаем только активные услуги, которые еще не добавлены
                                return AdditionalService::where('is_active', true)
                                    ->whereNotIn('id', $attachedServiceIds)
                                    ->orderBy('sort_order')
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->toArray();
                            })
                            ->searchable()
                            ->required()
                    )
                    ->form(fn(AttachAction $action): array => [
                        $action->getRecordSelect(),
                        TextInput::make('service_name')
                            ->label('Название услуги')
                            ->maxLength(255)
                            ->helperText('Можно изменить название для этого заказа'),
                        TextInput::make('price')
                            ->label('Цена услуги')
                            ->numeric()
                            ->prefix('₽')
                            ->step(0.01)
                            ->minValue(0)
                            ->helperText('Укажите цену услуги для данного заказа'),
                    ])
                    ->mutateFormDataUsing(function (array $data): array {
                        // Если услуга выбрана, получаем её данные
                        $serviceId = $data['recordId'] ?? null;
                        if ($serviceId) {
                            $service = AdditionalService::find($serviceId);
                            if ($service) {
                                // Устанавливаем название по умолчанию
                                if (empty($data['service_name'] ?? null)) {
                                    $data['service_name'] = $service->name;
                                }
                                // Устанавливаем цену по умолчанию, если не указана
                                if (!isset($data['price']) || $data['price'] === null || $data['price'] === '') {
                                    $order = $this->getOwnerRecord();
                                    $shippingLocation = $order->shippingLocation;
                                    if ($shippingLocation) {
                                        $data['price'] = $service->getPriceForLocation($shippingLocation) ?? $service->base_price ?? 0;
                                    } else {
                                        $data['price'] = $service->base_price ?? 0;
                                    }
                                }
                                // Устанавливаем тип цены и иконку
                                $data['price_type'] = $service->price_type;
                                $data['icon'] = $service->icon;
                            }
                        }
                        return $data;
                    })
                    ->using(function (array $data): void {
                        $order = $this->getOwnerRecord();
                        $serviceId = $data['recordId'] ?? null;
                        
                        if (!$serviceId) {
                            return;
                        }
                        
                        $service = AdditionalService::find($serviceId);
                        if (!$service) {
                            return;
                        }
                        
                        $order->additionalServices()->attach($serviceId, [
                            'service_name' => $data['service_name'] ?? $service->name,
                            'price' => $data['price'] ?? 0,
                            'price_type' => $data['price_type'] ?? $service->price_type,
                            'icon' => $data['icon'] ?? $service->icon,
                        ]);
                        
                        // Пересчитываем total заказа
                        $order->refresh();
                        $order->calculateTotal();
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->form(fn (EditAction $action): array => [
                        TextInput::make('service_name')
                            ->label('Название услуги')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('price')
                            ->label('Цена услуги')
                            ->numeric()
                            ->prefix('₽')
                            ->step(0.01)
                            ->minValue(0)
                            ->required(),
                    ])
                    ->mutateRecordDataUsing(function (array $data, $record): array {
                        // Загружаем данные из pivot таблицы
                        $order = $this->getOwnerRecord();
                        $pivot = $order->additionalServices()
                            ->where('additional_services.id', $record->id)
                            ->first()?->pivot;
                        
                        if ($pivot) {
                            $data['service_name'] = $pivot->service_name ?? $record->name;
                            $data['price'] = $pivot->price ?? 0;
                        }
                        
                        return $data;
                    })
                    ->using(function (array $data, $record): void {
                        // Обновляем данные в pivot таблице
                        $order = $this->getOwnerRecord();
                        $order->additionalServices()->updateExistingPivot($record->id, [
                            'service_name' => $data['service_name'],
                            'price' => $data['price'],
                        ]);
                        
                        // Пересчитываем total заказа
                        $order->calculateTotal();
                    }),
                DetachAction::make()
                    ->using(function ($record): void {
                        $order = $this->getOwnerRecord();
                        $order->additionalServices()->detach($record->id);
                        
                        // Пересчитываем total заказа
                        $order->calculateTotal();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make()
                        ->using(function ($records): void {
                            $order = $this->getOwnerRecord();
                            $serviceIds = $records->pluck('id')->toArray();
                            $order->additionalServices()->detach($serviceIds);
                            
                            // Пересчитываем total заказа
                            $order->calculateTotal();
                        }),
                ]),
            ]);
    }
}
