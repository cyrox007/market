<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductCollectionResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;

class EditProductCollection extends EditRecord
{
    protected static string $resource = ProductCollectionResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $data;
    }

    protected function afterSave(): void
    {
        $record = $this->record;
        
        // Если подборка автоматическая, синхронизируем товары по скоупу
        if ($record->is_auto && $record->scope_type) {
            $beforeCount = $record->products()->count();
            $record->syncProductsByScope();
            $record->flushHomeCaches();
            $afterCount = $record->fresh()->products()->count();

            Notification::make()
                ->title('Подборка синхронизирована')
                ->body($afterCount > 0
                    ? "Товаров в подборке: {$afterCount} (было {$beforeCount})."
                    : 'По выбранному скоупу не найдено активных товаров. Проверьте тип скоупа и каталог.')
                ->success()
                ->send();
        } elseif ($record->is_auto && ! $record->scope_type) {
            Notification::make()
                ->title('Укажите тип скоупа')
                ->body('Для автоматической подборки нужно выбрать тип скоупа и сохранить снова.')
                ->warning()
                ->send();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sync')
                ->label('Синхронизировать товары')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Синхронизировать товары в подборке')
                ->modalDescription('Это действие обновит список товаров в подборке на основе выбранного скоупа. Продолжить?')
                ->action(function () {
                    $record = $this->record;
                    
                    if (!$record->is_auto || !$record->scope_type) {
                        Notification::make()
                            ->title('Ошибка')
                            ->body('Синхронизация доступна только для автоматических подборок с выбранным типом скоупа.')
                            ->danger()
                            ->send();
                        return;
                    }
                    
                    $beforeCount = $record->products()->count();
                    $record->syncProductsByScope();
                    $record->flushHomeCaches();
                    $afterCount = $record->products()->count();
                    
                    Notification::make()
                        ->title('Синхронизация завершена')
                        ->body("Товары обновлены. Было: {$beforeCount}, стало: {$afterCount}")
                        ->success()
                        ->send();
                })
                ->visible(fn () => $this->record->is_auto && $this->record->scope_type),
            ...parent::getHeaderActions(),
        ];
    }
}
