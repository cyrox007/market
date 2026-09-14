@php
    $reviewOwner = $record->isVariant() && $record->parent_product_id ? $record->parentProduct : $record;
    $reviewsCount = $reviewOwner?->reviews()->count() ?? 0;
    $pendingReviewsCount = $reviewOwner?->reviews()->where('is_approved', false)->count() ?? 0;
    $rulesCount = $record->isVariant() ? $record->variantRegionRules()->count() : $record->regionRules()->count();
@endphp

<div id="product-related" class="scroll-mt-28">
    <x-filament::section>
        <x-slot name="heading">Связанные данные</x-slot>
        <x-slot name="description">Отзывы, правила продажи и товарные связи доступны отдельно от основной формы.</x-slot>

        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
            <x-filament::button type="button" color="gray" wire:click="openProductWorkspace('reviews')">
                Отзывы · {{ $reviewsCount }}@if ($pendingReviewsCount > 0) ({{ $pendingReviewsCount }} на модерации)@endif
            </x-filament::button>

            <x-filament::button type="button" color="gray" wire:click="openProductWorkspace('region-rules')">
                Правила продажи · {{ $rulesCount }}
            </x-filament::button>

            @if (! $record->isVariant())
                <x-filament::button type="button" color="gray" wire:click="openProductWorkspace('related-products')">
                    Сопутствующие товары · {{ $record->relatedProducts()->count() }}
                </x-filament::button>

                <x-filament::button type="button" color="gray" wire:click="openProductWorkspace('bundles')">
                    Наборы и комплекты · {{ $record->bundleProducts()->count() }}
                </x-filament::button>
            @endif
        </div>
    </x-filament::section>
</div>
