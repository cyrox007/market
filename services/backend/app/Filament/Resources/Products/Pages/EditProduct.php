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

    protected array $productAttributesData = [];

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            Action::make('viewOnSite')
                ->label('Просмотр на сайте')
                ->icon('heroicon-o-eye')
                ->url(fn () => url('/product/' . $this->record->slug))
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
                        ->title('Товар успешно обновлён из 1С')
                        ->success()
                        ->send();
                }),
        ];
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
        return __('filament/admin_sv/edit_product.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament/admin_sv/edit_product.title');
    }

    protected function getRedirectUrl(): ?string
    {
        return ProductResource::getUrl('index');
    }
}
