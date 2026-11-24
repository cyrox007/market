<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6">
        <x-filament::section heading="Категории и хранение">
            <p class="text-sm">Критические события отключить из панели нельзя. Сроки хранения задаются в днях; для безопасности, заказов и администрирования минимум 90 дней.</p>
            <label class="flex gap-3 items-center mt-4">
                <input type="checkbox" wire:model="behavior">
                <span>Регистрировать запросы каталога (без текста поиска)</span>
            </label>
            @foreach (['security'=>'Безопасность','admin'=>'Администрирование','order'=>'Заказы','account'=>'Аккаунт','shopping'=>'Покупки','behavior'=>'Каталог'] as $key => $label)
                <div class="mt-4">
                    <label for="retention-{{ $key }}" class="block text-sm font-medium">{{ $label }}</label>
                    <x-filament::input.wrapper>
                        <x-filament::input id="retention-{{ $key }}" type="number" min="{{ in_array($key, ['security','admin','order']) ? 90 : 7 }}" max="1095" wire:model="retentionDays.{{ $key }}" />
                    </x-filament::input.wrapper>
                    @error('retention_days.'.$key) <p class="text-danger-600 text-sm">{{ $message }}</p> @enderror
                </div>
            @endforeach
        </x-filament::section>
        <x-filament::button type="submit">Сохранить настройки</x-filament::button>
    </form>
</x-filament-panels::page>
