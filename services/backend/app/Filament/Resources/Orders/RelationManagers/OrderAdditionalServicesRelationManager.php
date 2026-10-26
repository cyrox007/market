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
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
