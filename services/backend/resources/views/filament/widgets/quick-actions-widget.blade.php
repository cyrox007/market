<x-filament-widgets::widget class="fi-quick-actions-widget">
    <x-filament::section :heading="$heading" :description="$description">
        @if (count($actions) > 0)
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
                @foreach ($actions as $action)
                    <x-filament::button tag="a" :href="$action['url']" :color="$action['color']" :icon="$action['icon']" outlined
                        size="sm" class="fi-quick-action-btn justify-center">
                        {{ $action['label'] }}
                    </x-filament::button>
                @endforeach
            </div>
        @else
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Нет доступных быстрых действий для вашей роли.
            </p>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
