<x-filament-panels::page>
    <x-filament::section heading="Хронология аккаунта #{{ $userId }}">
        <p class="text-sm text-gray-500 dark:text-gray-400">Новые события сверху. Гостевые действия показаны только при однозначной связи с аккаунтом.</p>
        <a class="text-sm text-primary-600 underline" href="{{ \App\Filament\Resources\ActivityAudit\ActivityAuditResource::getUrl('index', ['tableFilters'=>['actor'=>['id'=>$userId]]]) }}">Открыть таблицу с фильтрами</a>
        <ol class="mt-4 border-s border-gray-300 dark:border-gray-700 ms-2">
            @forelse ($events as $event)
                <li class="relative ps-5 pb-5" wire:key="audit-{{ $event->id }}">
                    <span class="absolute -start-1.5 top-2 h-3 w-3 rounded-full {{ $event->result === 'success' ? 'bg-success-500' : 'bg-warning-500' }}"></span>
                    <time class="text-xs text-gray-500">{{ $event->created_at?->timezone(config('app.admin_timezone','Europe/Moscow'))->format('d.m.Y H:i:s') }}</time>
                    <div class="font-medium">{{ $event->event_type }} · {{ $event->result }}</div>
                    <div class="text-sm text-gray-500">{{ $event->source }} · {{ $event->actor_type }} @if($event->subject_type) · {{ $event->subject_type }} #{{ $event->subject_id }} @endif</div>
                    @if($event->details)
                        <div class="text-xs break-all">{{ json_encode($event->details, JSON_UNESCAPED_UNICODE) }}</div>
                    @endif
                </li>
            @empty
                <li class="ps-5 py-4 text-gray-500">Нет зарегистрированных действий.</li>
            @endforelse
        </ol>
        {{ $events->links() }}
    </x-filament::section>
</x-filament-panels::page>
