<x-filament-panels::page>
    <x-filament::section
        heading="Складской учет"
        description="Управление режимом остатков по складам и fallback-логикой по локациям."
    >
        <div class="space-y-4">
            <label class="flex items-center gap-3">
                <input type="checkbox" wire:model="warehouseAccountingEnabled" class="rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500" />
                <span class="text-sm text-gray-800 dark:text-gray-200">Складской учет активирован</span>
            </label>

            <label class="flex items-center gap-3">
                <input type="checkbox" wire:model="fallbackToFirstWarehouse" class="rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500" />
                <span class="text-sm text-gray-800 dark:text-gray-200">Если нет привязки к локации — использовать первый доступный склад</span>
            </label>

            <x-filament::button color="primary" wire:click="saveWarehouseAccountingSettings">
                Сохранить настройки складского учета
            </x-filament::button>
        </div>
    </x-filament::section>

    <x-filament::section
        heading="Очереди"
        description="Перезапуск воркеров, очистка проваленных заданий и очереди. Воркеры должны быть запущены отдельно (supervisor, systemd или php artisan queue:work)."
    >
        <div class="flex flex-wrap gap-2">
            <x-filament::button
                color="gray"
                icon="heroicon-o-arrow-path"
                wire:click="queueRestart"
                wire:confirm="Отправить сигнал перезапуска воркерам? Они завершат текущие задачи и перезапустятся."
            >
                Перезапустить воркеры
            </x-filament::button>
            <x-filament::button
                color="gray"
                icon="heroicon-o-trash"
                wire:click="queueFlush"
                wire:confirm="Удалить все проваленные задания из списка?"
            >
                Очистить проваленные задания
            </x-filament::button>
            <x-filament::button
                color="gray"
                icon="heroicon-o-x-circle"
                wire:click="queueClear"
                wire:confirm="Удалить все ожидающие задания из очереди? Выполняющиеся задачи не затронуты."
            >
                Очистить очередь заданий
            </x-filament::button>
        </div>
    </x-filament::section>

    <x-filament::section
        heading="Кэш"
        description="Очистка кэша приложения, конфигурации, скомпилированных представлений и маршрутов."
    >
        <div class="flex flex-wrap gap-2">
            <x-filament::button
                color="primary"
                icon="heroicon-o-arrow-path"
                wire:click="clearAllCaches"
                wire:confirm="Очистить весь кэш (cache, config, view, route)?"
            >
                Сбросить весь кэш
            </x-filament::button>
            <x-filament::button
                color="gray"
                icon="heroicon-o-cube"
                wire:click="cacheClear"
            >
                Очистить кэш приложения
            </x-filament::button>
            <x-filament::button
                color="gray"
                icon="heroicon-o-cog-6-tooth"
                wire:click="configClear"
            >
                Очистить кэш конфигурации
            </x-filament::button>
            <x-filament::button
                color="gray"
                icon="heroicon-o-document"
                wire:click="viewClear"
            >
                Очистить скомпилированные view
            </x-filament::button>
            <x-filament::button
                color="gray"
                icon="heroicon-o-map"
                wire:click="routeClear"
            >
                Очистить кэш маршрутов
            </x-filament::button>
        </div>
    </x-filament::section>

    @if(auth()->user()?->can('viewAny service_tools') || auth()->user()?->can('delete_all_catalog'))
        <x-filament::section
            heading="Опасная зона"
            description="Необратимое удаление данных. Для удаления каталога нужно ввести слово подтверждения."
        >
            <div class="space-y-6">
                @if(auth()->user()?->can('delete_all_catalog'))
                    <div class="rounded-lg border border-danger-200 bg-danger-50/50 p-4 dark:border-danger-800 dark:bg-danger-950/20">
                        <p class="text-sm font-medium text-gray-950 dark:text-white mb-2">
                            Удалить все товары
                        </p>
                        <p class="text-xs text-gray-600 dark:text-gray-400 mb-3">
                            Будут безвозвратно удалены все товары (включая вариации). Категории не затрагиваются.
                        </p>
                        @if($showClearProductsForm)
                            <div class="flex flex-wrap items-end gap-2">
                                <div class="min-w-[200px]">
                                    <label for="clear-products-confirm" class="filament-forms-field-wrapper-label block text-sm font-medium mb-1">
                                        Введите «УДАЛИТЬ» для подтверждения
                                    </label>
                                    <input
                                        id="clear-products-confirm"
                                        type="text"
                                        wire:model="clearProductsConfirm"
                                        class="filament-input block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-white/5 dark:text-white text-sm"
                                        placeholder="УДАЛИТЬ"
                                    />
                                </div>
                                <x-filament::button color="danger" wire:click="clearProducts">
                                    Удалить товары
                                </x-filament::button>
                                <x-filament::button color="gray" wire:click="cancelClearProductsForm">
                                    Отмена
                                </x-filament::button>
                            </div>
                        @else
                            <x-filament::button
                                color="danger"
                                icon="heroicon-o-rectangle-stack"
                                wire:click="openClearProductsForm"
                            >
                                Удалить все товары
                            </x-filament::button>
                        @endif
                    </div>

                    <div class="rounded-lg border border-danger-200 bg-danger-50/50 p-4 dark:border-danger-800 dark:bg-danger-950/20">
                        <p class="text-sm font-medium text-gray-950 dark:text-white mb-2">
                            Удалить все категории
                        </p>
                        <p class="text-xs text-gray-600 dark:text-gray-400 mb-3">
                            Будут безвозвратно удалены все категории каталога. Товары останутся (у них сбросится привязка к категориям).
                        </p>
                        @if($showClearCategoriesForm)
                            <div class="flex flex-wrap items-end gap-2">
                                <div class="min-w-[200px]">
                                    <label for="clear-categories-confirm" class="filament-forms-field-wrapper-label block text-sm font-medium mb-1">
                                        Введите «УДАЛИТЬ» для подтверждения
                                    </label>
                                    <input
                                        id="clear-categories-confirm"
                                        type="text"
                                        wire:model="clearCategoriesConfirm"
                                        class="filament-input block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-white/5 dark:text-white text-sm"
                                        placeholder="УДАЛИТЬ"
                                    />
                                </div>
                                <x-filament::button color="danger" wire:click="clearCategories">
                                    Удалить категории
                                </x-filament::button>
                                <x-filament::button color="gray" wire:click="cancelClearCategoriesForm">
                                    Отмена
                                </x-filament::button>
                            </div>
                        @else
                            <x-filament::button
                                color="danger"
                                icon="heroicon-o-folder"
                                wire:click="openClearCategoriesForm"
                            >
                                Удалить все категории
                            </x-filament::button>
                        @endif
                    </div>
                @endif

                <div class="rounded-lg border border-danger-200 bg-danger-50/50 p-4 dark:border-danger-800 dark:bg-danger-950/20">
                    <p class="text-sm font-medium text-gray-950 dark:text-white mb-2">
                        Удалить все характеристики
                    </p>
                    <p class="text-xs text-gray-600 dark:text-gray-400 mb-3">
                        Будут удалены все характеристики (атрибуты) и их значения. Связи с товарами и категориями очистятся.
                    </p>
                    @if($showClearAttributesForm)
                        <div class="flex flex-wrap items-end gap-2">
                            <div class="min-w-[200px]">
                                <label for="clear-attributes-confirm" class="filament-forms-field-wrapper-label block text-sm font-medium mb-1">
                                    Введите «УДАЛИТЬ» для подтверждения
                                </label>
                                <input
                                    id="clear-attributes-confirm"
                                    type="text"
                                    wire:model="clearAttributesConfirm"
                                    class="filament-input block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-white/5 dark:text-white text-sm"
                                    placeholder="УДАЛИТЬ"
                                />
                            </div>
                            <x-filament::button color="danger" wire:click="clearAllAttributes">
                                Удалить характеристики
                            </x-filament::button>
                            <x-filament::button color="gray" wire:click="cancelClearAttributesForm">
                                Отмена
                            </x-filament::button>
                        </div>
                    @else
                        <x-filament::button
                            color="danger"
                            icon="heroicon-o-tag"
                            wire:click="openClearAttributesForm"
                        >
                            Удалить все характеристики
                        </x-filament::button>
                    @endif
                </div>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
