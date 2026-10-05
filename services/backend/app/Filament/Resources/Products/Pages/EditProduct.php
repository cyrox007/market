<?php

namespace App\Filament\Resources\Products\Pages;

use App\Actions\Inventory\Sync\RefreshProductStocksFrom1CAction;
use App\Filament\Resources\Products\ProductResource;
use App\Models\Product\Product;
use App\Services\Catalog\OneCProductSyncService;
use App\Services\Product\ProductAttributeSyncService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Str;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected array $productAttributesData = [];

    protected bool $stayAfterSave = false;

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction(),
            Action::make('saveAndStay')
                ->label('Сохранить и остаться')
                ->extraAttributes(['data-save-overlay' => true])
                ->action('saveAndStay'),
            $this->getCancelFormAction(),
        ];
    }

    public function saveAndStay(): void
    {
        $this->stayAfterSave = true;
        $wasVariable = (bool) $this->getRecord()->is_variable;

        $this->save();

        // Схема вкладок закэширована по товару до сохранения — пересобираем её
        if ($wasVariable !== (bool) $this->getRecord()->is_variable) {
            unset($this->cachedSchemas['content']);
            $this->activeRelationManager = $this->getRecord()->is_variable
                ? (string) array_key_first($this->getRelationManagers())
                : null;
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('openParentProduct')
                ->label('Корневой товар')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('info')
                ->tooltip(fn (): ?string => $this->record?->parentProduct
                    ? 'Открыть: ' . (string) $this->record->parentProduct->name
                    : null)
                ->url(fn (): ?string => $this->record?->parent_product_id
                    ? ProductResource::getUrl('edit', ['record' => $this->record->parent_product_id])
                    : null)
                ->visible(fn (): bool => (bool) $this->record?->parent_product_id),

            ActionGroup::make([
                Action::make('viewOnSite')
                    ->label('Просмотр на сайте')
                    ->icon('heroicon-o-eye')
                    ->url(fn () => url('/product/' . $this->record->slug))
                    ->openUrlInNewTab(),

                Action::make('refreshStocksFrom1C')
                    ->label('Обновить остатки из 1С')
                    ->icon('heroicon-o-arrow-path')
                    ->color('primary')
                    ->action(function (): void {
                        $result = app(RefreshProductStocksFrom1CAction::class)
                            ->execute($this->record);

                        $this->record->refresh();

                        if ($result['errors'] > 0) {
                            Notification::make()
                                ->title('Остатки обновлены частично')
                                ->body(
                                    "Обновлено: {$result['synced']}; "
                                    . "пропущено: {$result['skipped']}; "
                                    . "ошибок: {$result['errors']}."
                                )
                                ->warning()
                                ->send();

                            return;
                        }

                        if ($result['synced'] === 0) {
                            Notification::make()
                                ->title('Нечего обновлять')
                                ->body('У товара или его торговых предложений не указан код 1С.')
                                ->warning()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('Остатки обновлены из 1С')
                            ->body(
                                "Позиций: {$result['synced']}; "
                                . "получено складских строк: {$result['warehouse_rows']}."
                            )
                            ->success()
                            ->send();
                    }),

                Action::make('legacySyncFrom1C')
                    ->label('Legacy: полная загрузка из 1С')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Полная загрузка товара из 1С')
                    ->modalDescription('Это старый механизм. Он может изменить цену, контент, характеристики и структуру товара. Для обычной работы используйте «Обновить остатки из 1С».')
                    ->form([
                        TextInput::make('external_id')
                            ->label('Код товара в 1С')
                            ->default(fn (): ?string => $this->record?->external_id)
                            ->required(),
                    ])
                    ->action(function (array $data, $livewire) {
                        $externalId = $data['external_id'];
                        $service = app(OneCProductSyncService::class);
                        $product = $service->syncProductByExternalId($externalId);

                        if (! $product) {
                            Notification::make()
                                ->title('Товар не найден в 1С')
                                ->danger()
                                ->send();

                            return;
                        }

                        $livewire->form->fill($product->toArray());
                        $livewire->save();

                        Notification::make()
                            ->title('Legacy-синхронизация выполнена')
                            ->warning()
                            ->send();
                    }),

                DeleteAction::make()
                    ->label('Удалить'),
            ])
                ->label('Действия')
                ->icon('heroicon-o-ellipsis-horizontal')
                ->color('gray')
                ->button(),
        ];
    }

    /**
     * На странице редактирования товара хлебные крошки дублируют название товара
     * и занимают заметную высоту. Возврат к списку остаётся через навигацию/Cancel.
     */
    public function hasResourceBreadcrumbs(): bool
    {
        return false;
    }

    /**
     * Одна колонка в корне — двухколоночная раскладка задаётся в ProductClassicForm через Grid.
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
        $product = $this->record;
        if ($product && $product->variants()->count() > 0) {
            $data['is_variable'] = true;
        }

        $this->productAttributesData = $data['product_attributes'] ?? [];
        unset($data['product_attributes']);

        return $data;
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $product = $this->record;

        $data['excerpt'] = is_string($data['excerpt'] ?? null) ? $data['excerpt'] : '';
        $data['description'] = is_string($data['description'] ?? null) ? $data['description'] : '';

        if ($product) {
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

        if ($product) {
            $variantsCount = $product->variants()->count();
            if ($variantsCount > 0 && ! $product->is_variable) {
                $product->update(['is_variable' => true]);
            }

            $product->flushCache();

            if (config('cache.clear_catalog_on_product_change', true)) {
                Product::flushAllProductCaches();
            }
        }
    }

    public function getTitle(): string
    {
        if ($this->record?->isVariant()) {
            return 'ТП: ' . Str::limit((string) $this->record->name, 52);
        }

        return 'Товар: ' . Str::limit((string) $this->record?->name, 58);
    }

    public static function getNavigationLabel(): string
    {
        return __('filament/admin_sv/edit_product.title');
    }

    protected function getRedirectUrl(): ?string
    {
        return $this->stayAfterSave ? null : ProductResource::getUrl('index');
    }
}
