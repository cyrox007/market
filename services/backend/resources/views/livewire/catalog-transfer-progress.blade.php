<section class="ct-card" @if($run && $run->status === 'running') wire:poll.5s @endif>
<h2>2. Ход переноса</h2>
@if($run)
@php($labels=['draft'=>'Ожидание запуска','running'=>'В работе','paused'=>'Пауза','review'=>'Завершён','review_errors'=>'Завершён с замечаниями'])
<p><strong>{{ $run->apply_enabled?'Перенос':'Проверка' }} №{{ $run->id }} — {{ $labels[$run->status]??$run->status }}</strong></p><p>{{ $run->message }}</p>
<div class="ct-stages">
@if(!$run->apply_enabled)
<div class="ct-stage">Проверено строк с sku<strong>{{ $checkedTotal }} / {{ $codeTotal }}</strong>Осталось проверить: {{ $pendingTotal }}</div>
<div class="ct-stage">Найдены на новом сайте<strong>{{ $matchedTotal }}</strong>Не найдены: {{ $unmatchedTotal }}. В режиме проверки товары не создаются и не обновляются.</div>
<div class="ct-stage">Проверка API<strong>{{ $apiFoundTotal }} найдено</strong>Не найдены в API: {{ $apiMissingTotal }}. Сохранение остатков отключено в режиме проверки.</div>
@else
<div class="ct-stage">Товары из таблицы<strong>{{ $appliedTotal }} / {{ $eligibleTotal }}</strong>Создано {{ $createdTotal }}, обновлено {{ max(0,$appliedTotal-$createdTotal) }}</div>
<div class="ct-stage">Остатки и характеристики<strong>{{ $apiTotal }} / {{ $appliedTotal }}</strong><p>Не найдены в API: {{ $apiMissingTotal }}</p><p>Ошибки / требуется повтор: {{ $apiFailedTotal }}</p><p>Ещё не обработаны: {{ $apiPendingTotal }}</p></div>
<div class="ct-stage">Галереи и категории<strong>{{ $assetsTotal??'—' }} / {{ $appliedTotal }}</strong>Замечания — в строках ниже</div>
@endif
<div class="ct-stage">Без sku пропущено<strong>{{ $counts['skipped']??0 }}</strong>Конфликты {{ $counts['conflict']??0 }}, ошибки {{ $counts['error']??0 }}</div>
</div>
<p>Запросы к API: отправлено {{ $apiRequestTotal }}, получено ответов {{ $apiResponseTotal }} (журнал ведётся после обновления обработчика).</p>
@if($apiLastResponse)<p>{{ $apiLastResponse->message }} · код 1С {{ $apiLastResponse->code }}</p>@else<p class="ct-note">В этом запуске пока нет зарегистрированных ответов API. Это не подтверждение успешного получения остатков.</p>@endif
@if($run->apply_enabled)<p>Осталось сохранить товаров: {{ max(0,$eligibleTotal-$appliedTotal) }}. Без подтверждённых данных API: {{ max(0,$appliedTotal-$apiTotal) }}. Осталось проверить галерей: {{ max(0,$appliedTotal-$assetsTotal) }}.</p>@endif
@foreach($files as $file)<p class="ct-note">{{ $file->name }} — прочитано {{ max(0,$file->cursor-(json_decode($file->mapping,true)['header']??1)) }} / {{ $file->total }} строк. {{ $file->message }}</p>@endforeach
@if($currentAsset && $run->status==='running')<p>Галерея / категории: код 1С <strong>{{ $currentAsset->code }}</strong>.</p>@elseif($currentRow && $run->status==='running')<p>Обрабатывается код 1С <strong>{{ $currentRow->code }}</strong>.</p>@endif
<p class="ct-note">Последняя активность: {{ $run->heartbeat_at?\Illuminate\Support\Carbon::parse($run->heartbeat_at)->addHours(3)->format('d.m.Y H:i:s'):'ожидание обработчика' }} (Москва)</p>
<div class="ct-line">
@if(in_array($run->status,['draft','paused']))<x-filament::button color="gray" wire:click="start">Продолжить</x-filament::button>@endif
@if($run->status==='running')<x-filament::button color="gray" wire:click="pause">Пауза</x-filament::button>@endif
@if(in_array($run->status,['review','review_errors','paused']))<x-filament::button color="gray" wire:click="retryErrors">Повторить дополнение из источников</x-filament::button>@if($run->apply_enabled && $this->canApply())<x-filament::button color="gray" wire:click="continueAssets">Продолжить галереи</x-filament::button>@endif @endif
@if(!$run->apply_enabled && in_array($run->status,['review','review_errors']) && $this->canApply())<x-filament::button wire:click="applyRun" wire:confirm="Создать и обновить товары локально из таблиц?">Применить проверенные файлы</x-filament::button>@endif
</div>
@endif
</section>
