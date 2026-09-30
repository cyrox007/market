<x-filament-widgets::widget class="fi-one-c-stock-sync-widget">
    <x-filament::section
        heading="Остатки из 1С"
        description="Основной механизм интеграции с 1С: обновляет только остатки уже существующих товаров и торговых предложений по external_id."
    >
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-sm text-gray-700 dark:text-gray-300">
                    Позиций с кодом 1С: <strong>{{ $positionsWith1CCode }}</strong>
                </p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Названия, цены, категории, характеристики, фото и вариативность этим запуском не изменяются.
                </p>
            </div>

            <x-filament::button
                color="primary"
                icon="heroicon-o-arrow-path"
                wire:click="runStockSync"
                wire:confirm="Обновить остатки всех существующих позиций из 1С?"
            >
                Обновить все остатки
            </x-filament::button>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
