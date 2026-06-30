<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Actions\Inventory\Stock\ResolveWarehouseStockRowAction;
use App\Filament\Resources\Products\ProductResource;
use App\Models\Product\Product;
use App\Models\Settings\ProductStockSettings;
use App\Services\Inventory\WarehouseStockResolver;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Log;

class ProductBundleProductsRelationManager extends RelationManager
{
    protected static string $relationship = 'bundleProducts';

    protected static ?string $title = 'Входит в набор / комплект';

    protected static ?string $recordTitleAttribute = 'name';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('pivot.sort_order')
                    ->label('#')
                    ->numeric()
                    ->width('60px')
                    ->alignCenter(),

                SpatieMediaLibraryImageColumn::make('image')
                    ->collection('images')
                    ->conversion('thumb')
                    ->label('Изображение')
                    ->circular()
                    ->size(50)
                    ->defaultImageUrl(url('/images/placeholder.png')),

                TextColumn::make('name')
                    ->label('Название товара')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->wrap()
                    ->url(fn (Product $record): string => ProductResource::getUrl('edit', ['record' => $record]))
                    ->openUrlInNewTab(false),

                TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('SKU скопирован'),

                TextColumn::make('price')
                    ->label('Цена')
                    ->money('RUB')
                    ->sortable()
                    ->alignEnd(),

                TextColumn::make('stock_display')
                    ->label(fn (): string => ProductStockSettings::getInstance()->warehouse_accounting_enabled
                        ? 'Остаток (склады)'
                        : 'Остаток')
                    ->alignEnd()
                    ->state(fn (Product $record): int => self::resolveRowStock($record))
                    ->formatStateUsing(fn (int $state): string => $state . ' шт.')
                    ->color(fn (int $state): string => $state > 0 ? 'success' : 'danger'),
            ])
            ->headerActions([
                Action::make('attachBulk')
                    ->label('Добавить товары в набор')
                    ->icon('heroicon-o-plus')
                    ->form([
                        Select::make('product_ids')
                            ->label('Товары')
                            ->multiple()
                            ->searchable()
                            ->getSearchResultsUsing(function (string $search) {
                                $owner = $this->getOwnerRecord();
                                $existingIds = $owner->bundleProducts()->pluck('products.id')->toArray();

                                return Product::query()
                                    ->whereNull('parent_product_id')
                                    ->where('id', '!=', $owner->getKey())
                                    ->active()
                                    ->when(! empty($existingIds), fn ($q) => $q->whereNotIn('id', $existingIds))
                                    ->where(function ($query) use ($search) {
                                        $query->where('name', 'like', "%{$search}%")
                                            ->orWhere('sku', 'like', "%{$search}%");
                                    })
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(fn (Product $product) => [
                                        $product->id => $product->name . ' (SKU: ' . $product->sku . ')',
                                    ]);
                            })
                            ->getOptionLabelsUsing(function (array $values): array {
                                return Product::query()
                                    ->whereIn('id', $values)
                                    ->get()
                                    ->mapWithKeys(fn (Product $product) => [
                                        $product->id => $product->name . ' (SKU: ' . $product->sku . ')',
                                    ])
                                    ->all();
                            })
                            ->required()
                            ->minItems(1),
                    ])
                    ->action(function (array $data): void {
                        $owner = $this->getOwnerRecord();
                        $ids = array_map('intval', $data['product_ids'] ?? []);
                        $result = $owner->attachBundleProducts($ids);

                        Log::info('[ProductBundleRelationManager.attachBulk]', [
                            'owner_id' => $owner->getKey(),
                            'added_count' => count($result['attached']),
                        ]);

                        Notification::make()
                            ->title('Товары добавлены в набор')
                            ->body('Добавлено: ' . count($result['attached']))
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                EditAction::make()
                    ->label('Изменить порядок')
                    ->form([
                        TextInput::make('sort_order')
                            ->label('Порядок')
                            ->numeric()
                            ->required()
                            ->default(fn ($record) => $record->pivot->sort_order ?? 0),
                    ])
                    ->mutateRecordDataUsing(function (array $data, $record): array {
                        $data['sort_order'] = $record->pivot->sort_order ?? 0;

                        return $data;
                    })
                    ->using(function (array $data, $record): void {
                        $owner = $this->getOwnerRecord();
                        $owner->bundleProducts()->updateExistingPivot($record->id, [
                            'sort_order' => (int) ($data['sort_order'] ?? 0),
                        ]);
                        $owner->flushCache();
                        Product::flushAllProductCaches();
                    }),
                Action::make('detach')
                    ->label('Убрать из набора')
                    ->icon('heroicon-o-trash')
                    ->requiresConfirmation()
                    ->action(function (Product $record): void {
                        $this->getOwnerRecord()->detachBundleProduct($record);

                        Notification::make()
                            ->title('Товар убран из набора')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DetachBulkAction::make()
                        ->label('Убрать из набора')
                        ->action(function ($records): void {
                            $owner = $this->getOwnerRecord();
                            foreach ($records as $record) {
                                if ($record instanceof Product) {
                                    $owner->detachBundleProduct($record);
                                }
                            }

                            Notification::make()
                                ->title('Товары убраны из набора')
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->defaultSort('product_bundle_products.sort_order', 'asc')
            ->modifyQueryUsing(function ($query) {
                $owner = $this->getOwnerRecord();

                $settings = ProductStockSettings::getInstance();
                $with = ['media'];
                if ($settings->warehouse_accounting_enabled) {
                    $with[] = 'warehouseStocks';
                    $with[] = 'variants.warehouseStocks';
                } else {
                    $with[] = 'variants';
                }

                return $query
                    ->whereNull('parent_product_id')
                    ->where('products.id', '!=', $owner->getKey())
                    ->with($with)
                    ->orderBy('product_bundle_products.sort_order', 'asc');
            });
    }

    /**
     * Остаток как в списке товаров: вариативный — сумма по вариациям / склады; простой — stock или склады.
     */
    protected static function resolveRowStock(Product $record): int
    {
        $settings = ProductStockSettings::getInstance();

        if ($settings->warehouse_accounting_enabled) {
            if ($record->isVariable()) {
                return (int) round(app(WarehouseStockResolver::class)->resolveForProduct($record, null) ?? 0);
            }

            $row = app(ResolveWarehouseStockRowAction::class)->execute(
                $record,
                null,
                (bool) $settings->fallback_to_first_warehouse,
            );

            if ($row !== null) {
                return (int) round((float) $row->quantity);
            }

            return (int) ($record->getAttributes()['stock'] ?? 0);
        }

        if ($record->isVariable()) {
            return (int) $record->variants()->sum('stock');
        }

        return (int) ($record->stock ?? 0);
    }
}
