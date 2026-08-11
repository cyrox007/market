<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Импорт изображений товаров (Ozon)
        </x-slot>

        <x-slot name="description">
            Загрузите Excel-файл (шаблон Ozon). Система скачает главные и дополнительные фото и прикрепит их к товарам по артикулу.
        </x-slot>

        <form wire:submit.prevent="import" class="space-y-4">
            <div>
                <x-filament::input.wrapper>
                    <x-filament::input
                        type="file"
                        wire:model="file"
                        accept=".xlsx,.xls,.csv"
                        required
                    />
                </x-filament::input.wrapper>
                @error('file') <span class="text-danger-500 text-sm">{{ $message }}</span> @enderror
            </div>

            <div class="flex items-center gap-3">
                <x-filament::button
                    type="submit"
                    color="primary"
                    icon="heroicon-o-arrow-up-tray"
                    size="lg"
                    wire:loading.attr="disabled"
                    wire:loading.class="opacity-50 cursor-not-allowed"
                >
                    Запустить импорт
                </x-filament::button>

                <div wire:loading>
                    <span class="text-sm text-gray-500">Загрузка...</span>
                </div>
            </div>

            @if($lastImport)
                <div class="text-sm text-gray-500 mt-2">Последний запуск: {{ $lastImport }}</div>
            @endif
        </form>
    </x-filament::section>
</x-filament-widgets::widget>