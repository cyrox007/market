<x-filament-widgets::widget>
    <x-filament::section
        heading="Активность сотрудников"
        description="Последний подтверждённый раздел и редактирование. Вкладки обновляют присутствие каждые 25 секунд; без активности отметка исчезает."
    >
        <div wire:poll.30s="$refresh" class="space-y-3">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                    <span class="inline-flex h-2 w-2 rounded-full bg-green-500" aria-hidden="true"></span>
                    <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">
                        Сейчас активны: {{ $totalUsers }}
                    </span>
                </div>
                <span class="text-xs text-gray-500 dark:text-gray-400">
                    Обновлено {{ now()->format('H:i:s') }}
                </span>
            </div>

            @forelse ($activities as $activity)
                <div class="flex flex-col gap-2 rounded-lg border border-gray-200 p-3 dark:border-gray-700 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        <p class="font-semibold text-gray-900 dark:text-white">{{ $activity['name'] }}</p>
                        <p class="text-sm text-gray-700 dark:text-gray-200">{{ $activity['section'] }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            {{ $activity['action'] }}
                            @if ($activity['windows'] > 1)
                                · {{ $activity['windows'] }} активные вкладки
                            @endif
                        </p>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="text-xs text-gray-500 dark:text-gray-400" title="Время последнего подтверждения">
                            {{ $activity['last_seen']->diffForHumans() }}
                        </span>
                        @if ($activity['url'])
                            <a class="text-sm font-medium text-primary-600 hover:underline dark:text-primary-400"
                               href="{{ $activity['url'] }}">
                                Открыть раздел
                            </a>
                        @endif
                    </div>
                </div>
            @empty
                <div class="rounded-lg border border-dashed border-gray-300 p-5 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                    Сейчас нет сотрудников с подтверждённой активностью в административной панели.
                </div>
            @endforelse

            @if ($totalUsers > 5)
                <div class="flex items-center justify-between gap-3">
                    <span class="text-xs text-gray-500 dark:text-gray-400">
                        Показано {{ $visibleCount }} из {{ $totalUsers }}
                        @if ($showAll && $totalUsers > 50)
                            (не более 50 одновременно)
                        @endif
                    </span>
                    <x-filament::button size="sm" color="gray" outlined wire:click="toggleShowAll">
                        {{ $showAll ? 'Свернуть список' : 'Показать больше' }}
                    </x-filament::button>
                </div>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
