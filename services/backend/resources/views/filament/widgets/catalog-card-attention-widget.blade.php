<x-filament-widgets::widget>
    <x-filament::section heading="Карточки требуют внимания"
        description="Конкретные причины с учётом типа товара и наследования данных. Проверка выполняется в фоне.">
        @if (! $run)
            <div class="rounded-lg border border-dashed border-gray-300 p-5 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                Аудит ещё не завершался. После первого фонового запуска здесь появятся проблемы и дата проверки.
            </div>
        @else
            <div class="space-y-3">
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    Проверено {{ $run->checked_count }} карточек · {{ $run->finished_at?->format('d.m.Y H:i') }}
                    · {{ $run->files_checked_at ? 'файлы проверены' : 'без проверки физических файлов' }}
                </p>
                <div class="flex flex-wrap gap-3">
                    <a href="{{ $criticalUrl }}" class="rounded-lg border border-red-200 px-4 py-2 hover:underline dark:border-red-800">
                        <span class="font-bold text-red-600">{{ $criticalCount }}</span>
                        <span class="text-sm text-gray-700 dark:text-gray-200">Критично</span>
                    </a>
                    <a href="{{ $warningsUrl }}" class="rounded-lg border border-amber-200 px-4 py-2 hover:underline dark:border-amber-800">
                        <span class="font-bold text-amber-600">{{ $warningsCount }}</span>
                        <span class="text-sm text-gray-700 dark:text-gray-200">Несколько замечаний</span>
                    </a>
                </div>
                @forelse ($items as $item)
                    <div class="flex flex-col gap-2 border-t border-gray-200 pt-3 dark:border-gray-700 sm:flex-row sm:items-start sm:justify-between">
                        <div class="space-y-1">
                            <p class="font-semibold text-gray-900 dark:text-white">{{ $item['name'] }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $item['type'] }}</p>
                            <ul class="space-y-1">
                                @foreach ($item['reasons'] as $reason)
                                    <li class="text-xs {{ $reason['severity'] === 'critical' ? 'text-red-600 dark:text-red-400' : 'text-amber-700 dark:text-amber-400' }}">
                                        {{ $reason['severity'] === 'critical' ? 'Критично:' : 'Проверить:' }}
                                        {{ $reason['label'] }}
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                        @if ($item['url'])
                            <a href="{{ $item['url'] }}" class="shrink-0 text-sm font-medium text-primary-600 hover:underline dark:text-primary-400">
                                {{ auth()->user()?->can('update products') ? 'Редактировать' : 'Открыть' }}
                            </a>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Не найдено критичных ошибок или сочетаний нескольких замечаний по итогам проверки.
                    </p>
                @endforelse
                <a href="{{ $allUrl }}" class="inline-block text-sm font-medium text-primary-600 hover:underline dark:text-primary-400">
                    Полный список карточек
                </a>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
