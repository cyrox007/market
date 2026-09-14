@php
    use App\Filament\Resources\Products\Pages\EditProduct;
    use App\Filament\Resources\Products\RelationManagers\OperatorProductVariantsRelationManager;
    use App\Models\Product\Attribute;

    $selectedVariationAttributes = $record->variationAttributeSelection()
        ->orderBy('sort_order')
        ->orderBy('name')
        ->pluck('name');

    $usesGlobalVariationAttributes = $selectedVariationAttributes->isEmpty();

    if ($usesGlobalVariationAttributes) {
        $selectedVariationAttributes = Attribute::variationAttributes()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name');
    }
@endphp

<div id="product-variants" class="scroll-mt-28 space-y-4">
    <x-filament::section>
        <x-slot name="heading">Варианты</x-slot>
        <x-slot name="description">Торговые предложения этого товара: параметры, SKU, цена, остаток и статус.</x-slot>

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="text-sm text-gray-600 dark:text-gray-300">
                <span class="font-medium text-gray-950 dark:text-white">Параметры вариантов:</span>
                {{ $selectedVariationAttributes->isNotEmpty() ? $selectedVariationAttributes->implode(', ') : 'не настроены' }}
                @if ($usesGlobalVariationAttributes)
                    <span class="text-gray-500">(глобальные)</span>
                @endif
            </div>

            <x-filament::button
                type="button"
                color="gray"
                size="sm"
                icon="heroicon-m-adjustments-horizontal"
                wire:click="openProductWorkspace('variation-attributes')"
            >
                Изменить параметры
            </x-filament::button>
        </div>
    </x-filament::section>

    @livewire(OperatorProductVariantsRelationManager::class, [
        'ownerRecord' => $record,
        'pageClass' => EditProduct::class,
    ], key('inline-product-variants-' . $record->getKey()))
</div>
