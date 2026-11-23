<x-filament-widgets::widget>
    <x-filament::section>
        <div wire:poll.30s="$refresh" class="staff-presence">
            <header class="staff-presence__heading">
                <div class="staff-presence__heading-title">
                    <h2>Сейчас работают</h2>
                    <span class="staff-presence__count" aria-label="Активных сотрудников: {{ $totalUsers }}">{{ $totalUsers }}</span>
                </div>
                @if ($totalUsers > 5)
                    <x-filament::button size="sm" color="gray" outlined wire:click="toggleShowAll">
                        {{ $showAll ? 'Свернуть' : 'Все сотрудники' }}
                    </x-filament::button>
                @endif
            </header>

            <div class="staff-presence__grid">
                @forelse ($activities as $activity)
                    <article class="staff-presence__card {{ $activity['editing'] ? 'staff-presence__card--editing' : '' }}">
                        <div class="staff-presence__person">
                            <div class="staff-presence__avatar" aria-hidden="true">
                                {{ $activity['initials'] }}
                                <span class="staff-presence__online"></span>
                            </div>
                            <div class="staff-presence__identity">
                                <h3>{{ $activity['name'] }}</h3>
                                <span class="staff-presence__status">{{ $activity['label'] }}</span>
                            </div>
                            @if ($activity['editing'])
                                <x-filament::icon icon="heroicon-o-pencil-square" class="staff-presence__edit-icon" />
                            @endif
                        </div>
                        <div class="staff-presence__context">
                            @if ($activity['section'] !== $activity['subject'])
                                <p class="staff-presence__section">{{ $activity['section'] }}</p>
                            @endif
                            <p class="staff-presence__subject" title="{{ $activity['subject'] }}">{{ $activity['subject'] }}</p>
                        </div>
                        <footer class="staff-presence__footer">
                            <span title="Последняя активность: {{ $activity['last_seen']->format('d.m.Y H:i:s') }}">
                                {{ $activity['last_seen']->diffInSeconds(now(), absolute: true) < 60 ? 'Только что' : $activity['last_seen']->diffForHumans() }}
                                @if ($activity['windows'] > 1)
                                    <span class="staff-presence__tabs">· {{ $activity['windows'] }} активные вкладки</span>
                                @endif
                            </span>
                            @if ($activity['url'])
                                <a href="{{ $activity['url'] }}" class="staff-presence__link">{{ $activity['link_label'] }} <span aria-hidden="true">→</span></a>
                            @endif
                        </footer>
                    </article>
                @empty
                    <p class="staff-presence__empty">Сейчас нет сотрудников с активными вкладками.</p>
                @endforelse
            </div>
            @if ($showAll && $totalUsers > $visibleCount)
                <p class="staff-presence__limit">Показано {{ $visibleCount }} из {{ $totalUsers }} сотрудников</p>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
