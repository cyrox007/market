<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Models\Product\Product;
use App\Services\Catalog\OneCProductSyncService;
use App\Services\Product\ProductAttributeSyncService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    public static bool $formActionsAreSticky = true;

    protected bool $exitAfterSave = false;

    protected array $productAttributesData = [];

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            Action::make('viewOnSite')
                ->label('Просмотр на сайте')
                ->icon('heroicon-o-eye')
                ->url(fn() => url('/product/' . $this->record->slug))
                ->openUrlInNewTab(),

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
                    // Обновляем форму данными из товара
                    $livewire->form->fill($product->toArray());

                    // Сохраняем товар (обновляем)
                    $livewire->save();

                    Notification::make()
                        ->title('Товар успешно обновлён из 1С')
                        ->success()
                        ->send();
                }),
        ];
    }

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction()
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
        $this->save();
    }

    /**
     * Одна колонка в корне — раскладка внутри вкладок задаётся в ProductTabbedForm.
     */
    public function defaultForm(\Filament\Schemas\Schema $schema): \Filament\Schemas\Schema
    {
        return $schema
            ->columns(1)
            ->inlineLabel($this->hasInlineLabels())
            ->model($this->getRecord())
            ->operation('edit')
            ->statePath('data');
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Автоматически включаем is_variable, если у товара есть вариации
        $product = $this->record;
        if ($product && $product->variants()->count() > 0) {
            $data['is_variable'] = true;
        } elseif ($product && $product->variants()->count() === 0 && isset($data['is_variable']) && $data['is_variable']) {
            // Если пользователь пытается включить is_variable, но вариаций нет, оставляем как есть
            // (возможно, он планирует добавить вариации)
        }

        // Извлекаем характеристики для последующей обработки в afterSave
        // Удаляем их из данных, чтобы не пытаться сохранить напрямую
        $this->productAttributesData = $data['product_attributes'] ?? [];
        unset($data['product_attributes']);

        return $data;
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $product = $this->record;

        // RichEditor (TipTap) падает с "reading 'length'", если excerpt/description не строка (null/array/undefined).
        $data['excerpt'] = is_string($data['excerpt'] ?? null) ? $data['excerpt'] : '';
        $data['description'] = is_string($data['description'] ?? null) ? $data['description'] : '';

        if ($product) {
            // Загружаем существующие характеристики для формы
            $attributes = $product->attributes()->withPivot('attribute_value_id', 'custom_value')->get();
            $data['product_attributes'] = $attributes->map(function ($attribute) {
                return [
                    'attribute_id' => $attribute->id,
                    'attribute_value_id' => $attribute->pivot->attribute_value_id,
                    'custom_value' => $attribute->pivot->custom_value,
                ];
            })->toArray();
        } else {
            $data['product_attributes'] = [];
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $product = $this->record;
        if ($product) {
            app(ProductAttributeSyncService::class)->sync($product, $this->productAttributesData);
        }

        // Автоматически включаем is_variable, если появились вариации. НЕ сбрасываем в false при 0 вариациях —
        // пользователь может пометить товар как вариативный и затем добавить торговые предложения.
        if ($product) {
            $variantsCount = $product->variants()->count();
            if ($variantsCount > 0 && !$product->is_variable) {
                $product->update(['is_variable' => true]);
            }

            // Сбрасываем кэш товаров на бекенде сразу после сохранения
            $product->flushCache();

            // ВРЕМЕННО: полный сброс кэша каталога (убрать для высоконагруженных проектов)
            if (config('cache.clear_catalog_on_product_change', true)) {
                Product::flushAllProductCaches();
            }
        }
    }

    public function getTitle(): string
    {
        return __('filament/admin_sv/edit_product.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament/admin_sv/edit_product.title');
    }

    protected function getRedirectUrl(): ?string
    {
        if ($this->exitAfterSave) {
            return ProductResource::getUrl('index');
        }

        return parent::getRedirectUrl();
    }
}
