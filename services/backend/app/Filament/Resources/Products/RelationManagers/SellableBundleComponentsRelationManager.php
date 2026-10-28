<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Models\Product\Product;
use App\Services\Product\SellableBundle;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

class SellableBundleComponentsRelationManager extends RelationManager
{
    protected static string $relationship = 'sellableBundleComponents';
    protected static ?string $title = 'Состав продаваемого комплекта (конкретные SKU)';
    protected static ?string $recordTitleAttribute = 'name';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('pivot.sort_order')->label('#')->sortable(),
                TextColumn::make('name')->label('Компонент')->searchable(),
                TextColumn::make('sku')->label('Артикул SKU')->copyable(),
                TextColumn::make('pivot.quantity')->label('Штук на комплект')->numeric(),
                TextColumn::make('price')->label('Базовая цена SKU')->money('RUB'),
            ])
            ->headerActions([
                Action::make('toggleBundle')
                    ->label(fn (): string => $this->getOwnerRecord()->is_sellable_bundle
                        ? 'Отключить продажу комплектом' : 'Включить продажу комплектом')
                    ->icon('heroicon-o-shopping-cart')
                    ->color(fn (): string => $this->getOwnerRecord()->is_sellable_bundle ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->action(function (): void {
                        $owner = $this->getOwnerRecord();
                        if ($owner->isVariant()) {
                            throw ValidationException::withMessages(['bundle' => 'Комплект настраивается на корневой карточке.']);
                        }
                        if (! $owner->is_sellable_bundle && ! $owner->sellableBundleComponents()->exists()) {
                            throw ValidationException::withMessages(['bundle' => 'Сначала добавьте хотя бы один дополнительный SKU.']);
                        }
                        $owner->forceFill(['is_sellable_bundle' => ! $owner->is_sellable_bundle])->save();
                        Product::flushAllProductCaches();
                        Notification::make()->title('Режим продажи комплекта обновлён')->success()->send();
                    }),
                Action::make('attachSku')
                    ->label('Добавить SKU в состав')
                    ->icon('heroicon-o-plus')
                    ->form([
                        Select::make('component_product_id')
                            ->label('Торговое предложение или простой товар')
                            ->searchable()
                            ->getSearchResultsUsing(function (string $search): array {
                                $owner = $this->getOwnerRecord();
                                return Product::active()->with('parentProduct')
                                    ->where('id', '!=', $owner->id)
                                    ->where(fn ($q) => $q->where('name', 'like', '%'.$search.'%')
                                        ->orWhere('sku', 'like', '%'.$search.'%'))
                                    ->limit(80)->get()
                                    ->filter(fn (Product $sku) => app(SellableBundle::class)->componentIsSellable($sku, $owner))
                                    ->mapWithKeys(fn (Product $sku) => [
                                        $sku->id => ($sku->parentProduct?->name ?? $sku->name).' / '.$sku->name.' ('.$sku->sku.')',
                                    ])->all();
                            })
                            ->getOptionLabelUsing(fn ($id): ?string => Product::find($id)?->name)
                            ->required(),
                        TextInput::make('quantity')->label('Количество на комплект')
                            ->integer()->minValue(1)->maxValue(1000)->default(1)->required(),
                        TextInput::make('sort_order')->label('Порядок')
                            ->integer()->minValue(0)->default(0)->required(),
                    ])
                    ->action(function (array $data): void {
                        $owner = $this->getOwnerRecord();
                        if ($owner->isVariant()) {
                            throw ValidationException::withMessages(['bundle' => 'Комплект настраивается у родительского товара.']);
                        }
                        $sku = Product::with('parentProduct')->findOrFail($data['component_product_id']);
                        if (! app(SellableBundle::class)->componentIsSellable($sku, $owner)) {
                            throw ValidationException::withMessages(['component_product_id' => 'Выберите конкретный активный SKU.']);
                        }
                        if ($owner->sellableBundleComponents()->where('products.id', $sku->id)->exists()) {
                            throw ValidationException::withMessages(['component_product_id' => 'SKU уже есть в составе.']);
                        }
                        if ($owner->sellableBundleComponents()->count() >= 29) {
                            throw ValidationException::withMessages(['component_product_id' => 'Не более 29 дополнительных SKU на комплект.']);
                        }
                        $owner->sellableBundleComponents()->attach($sku->id, [
                            'quantity' => (int) $data['quantity'],
                            'sort_order' => (int) $data['sort_order'],
                        ]);
                        $owner->flushCache();
                        Product::flushAllProductCaches();
                        Notification::make()->title('SKU добавлен в комплект')->success()->send();
                    }),
            ])
            ->recordActions([
                Action::make('editQuantity')
                    ->label('Количество и порядок')
                    ->icon('heroicon-o-pencil')
                    ->fillForm(fn (Product $record): array => [
                        'quantity' => (int) $record->pivot->quantity,
                        'sort_order' => (int) $record->pivot->sort_order,
                    ])
                    ->form([
                        TextInput::make('quantity')->integer()->minValue(1)->maxValue(1000)->required(),
                        TextInput::make('sort_order')->integer()->minValue(0)->required(),
                    ])
                    ->action(function (Product $record, array $data): void {
                        $owner = $this->getOwnerRecord();
                        $owner->sellableBundleComponents()->updateExistingPivot($record->id, [
                            'quantity' => (int) $data['quantity'], 'sort_order' => (int) $data['sort_order'],
                        ]);
                        $owner->flushCache();
                        Product::flushAllProductCaches();
                    }),
                Action::make('removeSku')
                    ->label('Убрать')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (Product $record): void {
                        $owner = $this->getOwnerRecord();
                        $owner->sellableBundleComponents()->detach($record->id);
                        if ($owner->is_sellable_bundle && ! $owner->sellableBundleComponents()->exists()) {
                            $owner->forceFill(['is_sellable_bundle' => false])->save();
                        }
                        $owner->flushCache();
                        Product::flushAllProductCaches();
                    }),
            ])
            ->defaultSort('sellable_bundle_components.sort_order', 'asc');
    }
}
