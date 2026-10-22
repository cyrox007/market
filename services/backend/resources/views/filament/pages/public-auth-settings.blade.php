<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6 max-w-3xl">
        <x-filament::section heading="Публичная часть сайта">
            <div class="space-y-6">
                <div>
                    <label class="flex items-center gap-3 font-medium">
                        <input type="checkbox" wire:model="registrationEnabled" />
                        Разрешить регистрацию покупателей
                    </label>
                    <p class="mt-2 text-sm text-gray-500">Необязательный переключатель. При отключении новые аккаунты не создаются ни через форму, ни автоматически при гостевом заказе. Гостевые заказы остаются доступны.</p>
                </div>
                <div>
                    <label class="flex items-center gap-3 font-medium">
                        <input type="checkbox" wire:model="loginEnabled" />
                        Разрешить вход покупателей
                    </label>
                    <p class="mt-2 text-sm text-gray-500">При отключении запрещены новые входы через публичный API, включая автоматический вход после регистрации или заказа. Действующие сессии сохраняются. Вход сотрудников в админку не затрагивается.</p>
                </div>
            </div>
        </x-filament::section>
        <x-filament::button type="submit" wire:loading.attr="disabled">Сохранить</x-filament::button>
    </form>
</x-filament-panels::page>
