<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\Products\RelationManagers\OperatorProductVariationAttributeSelectionRelationManager;
use App\Filament\Resources\Products\RelationManagers\ProductBundleProductsRelationManager;
use App\Filament\Resources\Products\RelationManagers\ProductRegionRulesRelationManager;
use App\Filament\Resources\Products\RelationManagers\ProductReviewsRelationManager;
use App\Filament\Resources\Products\RelationManagers\RelatedProductsRelationManager;
use App\Filament\Resources\Products\RelationManagers\VariantRegionRulesRelationManager;
use App\Models\Product\Product;
use App\Services\Catalog\OneCProductSyncService;
use App\Services\Product\ProductAttributeSyncService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Livewire as LivewireComponent;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Livewire\Attributes\Url;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected array $productAttributesData = [];

    #[Url(as: 'workspace')]
    public ?string $productWorkspace = null;

    protected function getHeaderActions(): array
    {
        return [
            $this->getSaveFormAction()
                ->label('Сохранить')
                ->icon('heroicon-m-check')
                ->formId('form'),

            Action::make('viewOnSite')
                ->label('Просмотр на сайте')
                ->icon('heroicon-o-eye')
                ->url(fn () => url('/product/' . $this->record->slug))
                ->openUrlInNewTab(),

            Action::make('syncFrom1C')
                ->label('Загрузить из 1С')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
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
                        Notification::make()->title('Товар не найден в 1С')->danger()->send();
                        return;
                    }

                    $livewire->form->fill($product->toArray());
                    $livewire->save();

                    Notification::make()->title('Товар успешно обновлён из 1С')->success()->send();
                }),

            DeleteAction::make()->label('Удалить'),
        ];
    }

    public function content(Schema $schema): Schema
    {
        $workspace = $this->getActiveProductWorkspace();

        if ($workspace !== null) {
            return $schema->components([
                View::make('filament.resources.products.components.product-workspace-header')
                    ->viewData([
                        'title' => $workspace['title'],
                        'description' => $workspace['description'],
                    ])
                    ->columnSpanFull(),
                LivewireComponent::make($workspace['manager'], [
                    'ownerRecord' => $this->getRecord(),
                    'pageClass' => static::class,
                ])
                    ->key('product-workspace-' . $this->productWorkspace . '-' . $this->getRecord()->getKey())
                    ->columnSpanFull(),
            ]);
        }

        return $schema->components([
            View::make('filament.resources.products.components.editor-section-nav')->columnSpanFull(),
            $this->getFormContentComponent(),
        ]);
    }

    public function openProductWorkspace(string $workspace): void
    {
        if (array_key_exists($workspace, $this->getProductWorkspaceDefinitions())) {
            $this->productWorkspace = $workspace;
        }
    }

    public function closeProductWorkspace(): void
    {
        $this->productWorkspace = null;
    }

    protected function getProductWorkspaceDefinitions(): array
    {
        $regionRulesManager = $this->getRecord()->isVariant()
            ? VariantRegionRulesRelationManager::class
            : ProductRegionRulesRelationManager::class;

        return [
            'variation-attributes' => [
                'title' => 'Параметры вариаций',
                'description' => 'Выберите характеристики, которые отличают варианты этого товара.',
                'manager' => OperatorProductVariationAttributeSelectionRelationManager::class,
            ],
            'reviews' => [
                'title' => 'Отзывы товара',
                'description' => 'Просмотр, модерация и редактирование отзывов этого товара.',
                'manager' => ProductReviewsRelationManager::class,
            ],
            'region-rules' => [
                'title' => 'Правила продажи',
                'description' => 'Региональные цены, видимость товара и сроки доставки.',
                'manager' => $regionRulesManager,
            ],
            'related-products' => [
                'title' => 'Сопутствующие товары',
                'description' => 'Связанные товары, которые показываются покупателю рядом с текущим товаром.',
                'manager' => RelatedProductsRelationManager::class,
            ],
            'bundles' => [
                'title' => 'Наборы и комплекты',
                'description' => 'Состав набора и порядок связанных товаров.',
                'manager' => ProductBundleProductsRelationManager::class,
            ],
        ];
    }

    protected function getActiveProductWorkspace(): ?array
    {
        if ($this->productWorkspace === null) {
            return null;
        }

        return $this->getProductWorkspaceDefinitions()[$this->productWorkspace] ?? null;
    }

    protected function getFormActions(): array
    {
        return [];
    }

    public function defaultForm(Schema $schema): Schema
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
        return null;
    }
}
