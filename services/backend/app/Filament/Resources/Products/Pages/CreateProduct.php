<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Models\Product\Product;
use App\Services\Catalog\OneCProductSyncService;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    public static bool $formActionsAreSticky = true;

    protected bool $exitAfterSave = false;

    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction()
                ->label('Сохранить')
                ->icon('heroicon-m-check'),
            Action::make('saveAndExit')
                ->label('Сохранить и выйти')
                ->icon('heroicon-m-arrow-right-start-on-rectangle')
                ->color('gray')
                ->action('saveAndExit')
                ->keyBindings(['mod+shift+s']),
            $this->getCancelFormAction(),
        ];
    }

    public function saveAndExit(): void
    {
        $this->exitAfterSave = true;
        $this->create();
    }

    /**
     * Перед созданием товара гарантируем, что поля,
     * которые не могут быть NULL в БД, не уйдут как null.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // SKU в таблице products не допускает NULL, поэтому
        // подставляем пустую строку, если поле не заполнено.
        // После создания модель Product::created запишет ID, если sku пустой.
        if (!array_key_exists('sku', $data) || $data['sku'] === null) {
            $data['sku'] = '';
        }

        return $data;
    }

    /**
     * Обработка создания с уведомлением об ошибках валидации
     */
    protected function handleRecordCreation(array $data): Product
    {
        try {
            return parent::handleRecordCreation($data);
        } catch (ValidationException $e) {
            Notification::make()
                ->title('Ошибка валидации')
                ->danger()
                ->body('Пожалуйста, исправьте ошибки в форме.')
                ->send();

            throw $e;
        }
    }

    protected function afterCreate(): void
    {
        // Сбрасываем кэш товаров на бекенде сразу после создания
        $this->record->flushCache();

        // ВРЕМЕННО: полный сброс кэша каталога (убрать для высоконагруженных проектов)
        if (config('cache.clear_catalog_on_product_change', true)) {
            Product::flushAllProductCaches();
        }
    }

    protected function getRedirectUrl(): string
    {
        if ($this->exitAfterSave) {
            return ProductResource::getUrl('index');
        }

        return ProductResource::getUrl('edit', ['record' => $this->getRecord()]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('syncFrom1C')
                ->label('Загрузить из 1С')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('primary')
                ->form([
                    TextInput::make('external_id')
                        ->label('Код товара в 1С')
                        ->required()
                        ->helperText('Введите external_id товара из системы 1С'),
                ])
                ->action(function (array $data, $livewire) {
                    $externalId = $data['external_id'];
                    $service = app(OneCProductSyncService::class);
                    $product = $service->syncProductByExternalId($externalId);
                    if (!$product) {
                        Notification::make()
                            ->title('Товар не найден в 1С')
                            ->danger()
                            ->send();
                        return;
                    }

                    Notification::make()
                        ->title('Товар успешно загружен из 1С')
                        ->success()
                        ->send();

                    // Перенаправляем на редактирование созданного товара
                    $livewire->redirect(route('filament.admin_sv.resources.products.edit', ['record' => $product->id]));
                }),
        ];
    }
}
