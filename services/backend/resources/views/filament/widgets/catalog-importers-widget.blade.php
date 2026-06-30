<x-filament-widgets::widget class="fi-catalog-importers-widget">
    <x-filament::section :heading="$heading" :description="$description">
        @if (count($importers) > 0)
            <div class="space-y-4">
                @foreach ($importers as $index => $importer)
                    <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-gray-950 dark:text-white">
                                    {{ $importer['label'] }}
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                    {{ $importer['class'] }}
                                </p>
                                @if (!empty($importer['last_run']))
                                    @php $run = $importer['last_run']; @endphp
                                    <p class="text-xs mt-2 text-gray-500 dark:text-gray-400">
                                        Последний запуск: {{ $run->finished_at?->diffForHumans() ?? $run->started_at->diffForHumans() }}
                                        — @if($run->status === 'success')
                                            <span class="text-success-600 dark:text-success-400">успешно</span>
                                            (категории: {{ $run->created_categories }}+{{ $run->updated_categories }}, товары: {{ $run->created_products }}+{{ $run->updated_products }}, {{ $run->duration_seconds }} с)
                                        @else
                                            <span class="text-danger-600 dark:text-danger-400">ошибка</span>
                                            @if($run->error_message) — {{ Str::limit($run->error_message, 80) }} @endif
                                        @endif
                                    </p>
                                @endif
                            </div>
                            <x-filament::button
                                color="primary"
                                icon="heroicon-o-arrow-path"
                                wire:click="runImport({{ $index }})"
                                wire:confirm="Запустить импорт? Задача будет поставлена в очередь и выполнится в фоне."
                            >
                                Поставить в очередь
                            </x-filament::button>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Нет зарегистрированных классов импорта. Добавьте классы в <code>config/catalog_import.php</code>.
            </p>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
