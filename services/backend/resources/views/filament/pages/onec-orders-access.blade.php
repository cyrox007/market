<x-filament-panels::page>
    <div class="space-y-6">
        <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <h2 class="text-lg font-semibold">Доступ 1С к заказам</h2>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                Чтение: GET <code>/api/v1/integration/1c/orders</code>, право <code>orders:read</code>.
                Обновление статуса: POST <code>/api/v1/integration/1c/orders/{orderId}/status</code>,
                отдельное право <code>orders:status:write</code> и серверный флаг включения.
                Токен чтения не разрешает запись, а токен записи не даёт доступа к списку заказов.
                Оплата, отмена и произвольное изменение заказа через этот API недоступны.
            </p>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                Документация: <code>market-docs/onec-direct-orders-read-api.md</code>.
                После выпуска передайте секрет разработчику 1С по защищённому каналу.
            </p>
        </section>

        @if ($issuedToken !== null)
            <section role="status" class="rounded-xl border-2 border-amber-500 bg-amber-50 p-6 dark:bg-amber-950/20">
                <h2 class="text-lg font-semibold">Новый токен — скопируйте сейчас</h2>
                <p class="mt-2 text-sm">
                    Секрет показывается однократно: после обновления страницы или закрытия этого блока его
                    нельзя восстановить. В базе хранится только хеш. Не отправляйте его в чат или в URL.
                </p>
                <label for="one-c-issued-token" class="mt-4 block text-sm font-medium">X-1C-Orders-Token</label>
                <input id="one-c-issued-token" type="text" readonly
                       onclick="this.select()"
                       class="mt-1 w-full rounded-lg border border-gray-400 bg-white p-3 font-mono text-sm text-gray-900"
                       value="{{ $issuedToken }}" aria-label="Скопировать выданный токен">
                <div class="mt-3">
                    <x-filament::button color="gray" wire:click="dismissIssuedToken">
                        Я сохранил токен — скрыть
                    </x-filament::button>
                </div>
            </section>
        @endif

        <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <h2 class="text-lg font-semibold">Выпустить токен для 1С</h2>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                Выпускайте отдельный токен для каждой базы 1С: рабочей и тестовой.
                Отзыв одного токена не затронет остальные.
            </p>
            <form wire:submit.prevent="issueToken" class="mt-4 grid gap-4 md:grid-cols-2">
                <div class="md:col-span-2">
                    <label for="one-c-token-name" class="block text-sm font-medium">Название доступа</label>
                    <input id="one-c-token-name" type="text" maxlength="120" required
                           class="mt-1 w-full rounded-lg border border-gray-400 bg-white p-2 text-gray-900 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                           placeholder="Например: 1С — основная база" wire:model="name">
                    @error('name') <p role="alert" class="text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="one-c-token-scope" class="block text-sm font-medium">Право доступа</label>
                    <select id="one-c-token-scope" wire:model="scope"
                            class="mt-1 w-full rounded-lg border border-gray-400 bg-white p-2 text-gray-900 dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                        <option value="orders:read">Только чтение заказов</option>
                        <option value="orders:status:write">Только обновление статуса</option>
                    </select>
                    @error('scope') <p role="alert" class="text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="one-c-token-expiry" class="block text-sm font-medium">Дата истечения (UTC)</label>
                    <input id="one-c-token-expiry" type="date"
                           class="mt-1 w-full rounded-lg border border-gray-400 bg-white p-2 text-gray-900 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                           wire:model="expiresOn">
                    <p class="mt-1 text-xs text-gray-500">Необязательно. До 23:59:59 UTC указанной даты.</p>
                    @error('expiresOn') <p role="alert" class="text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="one-c-token-ips" class="block text-sm font-medium">Разрешённые IP 1С</label>
                    <input id="one-c-token-ips" type="text" maxlength="1000"
                           class="mt-1 w-full rounded-lg border border-gray-400 bg-white p-2 text-gray-900 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                           placeholder="203.0.113.12, 203.0.113.13" wire:model="allowedIps">
                    <p class="mt-1 text-xs text-gray-500">
                        Необязательно; до 20 точных IPv4/IPv6, разделённых запятыми.
                        Если 1С выходит через NAT или прокси, укажите внешний IP.
                    </p>
                    @error('allowedIps') <p role="alert" class="text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="md:col-span-2">
                    <x-filament::button type="submit" wire:loading.attr="disabled" wire:target="issueToken">
                        Выпустить токен с выбранным правом
                    </x-filament::button>
                </div>
            </form>
        </section>

        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <h2 class="text-lg font-semibold">Выданные токены</h2>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                Отображаются последние 100 записей. Секреты здесь не показываются, только безопасная подсказка.
            </p>
            <div class="mt-4 overflow-x-auto">
                <table class="w-full min-w-[780px] text-left text-sm">
                    <thead>
                        <tr class="border-b dark:border-gray-700">
                            <th class="p-2">Название</th>
                            <th class="p-2">Право / подсказка</th>
                            <th class="p-2">Срок и IP-ограничение</th>
                            <th class="p-2">Последнее обращение</th>
                            <th class="p-2">Состояние</th>
                            <th class="p-2">Действие</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($tokens as $token)
                            @php
                                $expired = $token->expires_at !== null && \Illuminate\Support\Carbon::parse($token->expires_at, 'UTC')->isPast();
                                $revoked = $token->revoked_at !== null;
                                $ips = json_decode($token->allowed_ips ?? '[]', true) ?: [];
                            @endphp
                            <tr wire:key="onec-credential-{{ $token->id }}" class="border-b align-top dark:border-gray-800">
                                <td class="p-2 font-medium">{{ $token->name }}<div class="text-xs text-gray-500">№ {{ $token->id }} · {{ $token->created_at }}</div></td>
                                <td class="p-2"><code>{{ implode(', ', json_decode($token->abilities ?? '[]', true) ?: []) }}</code><div class="text-xs text-gray-500">{{ $token->token_hint }}…</div></td>
                                <td class="p-2">{{ $token->expires_at ?? 'Бессрочно' }}
                                    <div class="text-xs text-gray-500">{{ $ips ? implode(', ', $ips) : 'Любой IP' }}</div>
                                </td>
                                <td class="p-2">{{ $token->last_used_at ?? 'Не использовался' }}
                                    <div class="text-xs text-gray-500">{{ $token->last_used_ip ?? '' }}</div>
                                </td>
                                <td class="p-2">{{ $revoked ? 'Отозван' : ($expired ? 'Истёк' : 'Активен') }}</td>
                                <td class="p-2">
                                    @unless ($revoked)
                                        <x-filament::button color="danger" size="sm"
                                            wire:click="revokeToken({{ $token->id }})"
                                            wire:confirm="Отозвать токен «{{ $token->name }}»? Он перестанет работать сразу.">
                                            Отозвать
                                        </x-filament::button>
                                    @endunless
                                </td>
                            </tr>
                        @empty
                            <tr><td class="p-3" colspan="6">Токены ещё не выпускались.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <h2 class="text-lg font-semibold">Журнал выдачи и отзыва</h2>
            <div class="mt-3 space-y-2 text-sm">
                @forelse ($events as $event)
                    <p>Токен №{{ $event->token_id }} · {{ $event->event === 'issued' ? 'выпущен' : 'отозван' }}
                        · {{ $event->created_at }} · сотрудник №{{ $event->actor_id ?? '—' }}
                        · IP {{ $event->ip ?? '—' }}</p>
                @empty
                    <p>Действий пока не было.</p>
                @endforelse
            </div>
        </section>
    </div>
</x-filament-panels::page>
