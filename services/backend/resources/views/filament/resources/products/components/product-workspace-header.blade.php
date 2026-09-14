<x-filament::section>
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="text-base font-semibold text-gray-950 dark:text-white">{{ $title }}</div>
            @if (! empty($description))
                <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $description }}</div>
            @endif
        </div>

        <x-filament::button
            type="button"
            color="gray"
            icon="heroicon-m-arrow-left"
            wire:click="closeProductWorkspace"
        >
            Назад к товару
        </x-filament::button>
    </div>
</x-filament::section>
