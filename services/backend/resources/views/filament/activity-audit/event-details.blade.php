@php
    use App\Filament\Resources\ActivityAudit\ActivityAuditPresentation;

    $rows = [
        'Кто' => ActivityAuditPresentation::actor($event),
        'Когда' => $event->created_at?->timezone(config('app.admin_timezone', 'Europe/Moscow'))->format('d.m.Y H:i:s'),
        'Источник' => ActivityAuditPresentation::SOURCES[$event->source] ?? $event->source,
        'Объект' => ActivityAuditPresentation::subject($event),
        'Результат' => ActivityAuditPresentation::RESULTS[$event->result] ?? $event->result,
        'Поля' => ActivityAuditPresentation::summary($event),
        ...ActivityAuditPresentation::details($event),
        'Код события' => $event->event_type,
        'ID события' => $event->event_id,
        'ID запроса' => $event->request_id,
    ];
@endphp

<dl class="divide-y divide-gray-200 dark:divide-white/10">
    @foreach ($rows as $label => $value)
        @continue($value === null || $value === '')
        <div class="grid gap-1 py-2 sm:grid-cols-3 sm:gap-3">
            <dt class="text-sm text-gray-600 dark:text-gray-400">{{ $label }}</dt>
            <dd class="break-words text-sm sm:col-span-2">{{ $value }}</dd>
        </div>
    @endforeach
</dl>
