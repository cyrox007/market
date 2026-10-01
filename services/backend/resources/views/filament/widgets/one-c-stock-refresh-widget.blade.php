<x-filament-widgets::widget class="fi-one-c-stock-refresh-widget">
    <x-filament::section
        heading="Остатки из 1С"
        description="Основной рабочий механизм 1С: обновляет только складские остатки существующих товаров и торговых предложений."
    >
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-sm text-gray-700 dark:text-gray-300">
                    Позиций с кодом 1С: <strong>{{ $positionsWithExternalId }}</strong>
                </p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Названия, цены, описания, категории, характеристики, фото и вариативность этим запуском не изменяются.
                </p>
            </div>

            <x-filament::button
                color="primary"
                icon="heroicon-o-arrow-path"
                wire:click="refreshAllStocks"
                wire:confirm="Поставить обновление остатков всех товаров в очередь?"
            >
                Обновить все остатки
            </x-filament::button>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
