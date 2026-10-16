@push('styles')
<style>
.ct-wrap{display:grid;gap:20px}.ct-card{padding:20px;border:1px solid #8885;border-radius:12px;background:#8881}.ct-card h2{font-size:18px;font-weight:600;margin-bottom:12px}.ct-line{display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin:12px 0}.ct-note{color:#9ca3af;font-size:12px}.ct-warning{padding:14px;border:1px solid #d59c30;border-radius:8px;background:#d59c3015}.ct-stages{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px;margin:16px 0}.ct-stage{padding:14px;background:#8882;border-radius:8px}.ct-stage strong{display:block;font-size:22px}.ct-table{width:100%;border-collapse:collapse}.ct-table th,.ct-table td{text-align:left;padding:12px 10px;border-bottom:1px solid #8884;vertical-align:top}.ct-table th{color:#9ca3af}.ct-input{padding:8px 12px;border:1px solid #8886;border-radius:8px;background:transparent;color:inherit;max-width:100%}.ct-table-scroll{overflow-x:auto}.ct-card details{margin:8px 0}.ct-card pre{max-height:240px;overflow:auto;white-space:pre-wrap;overflow-wrap:anywhere;font-size:12px}
</style>
@endpush
<x-filament-panels::page>
<div class="ct-wrap">
<section class="ct-card">
<h2>1. Загрузить и перенести товары</h2>
<p>Таблица — источник истины. Строки с заполненным sku создают или обновляют товар; API дополняет складские остатки и характеристики; старый сайт — категории и всю галерею.</p>
<form wire:submit="createRun">
<div class="ct-line"><input type="file" multiple accept=".xls,.xlsx" wire:model="uploads" aria-label="Таблицы XLS и XLSX"></div>
<p wire:loading wire:target="uploads">Читаем заголовки файлов…</p>
@foreach($uploads as $index=>$upload)
<div wire:key="upload-{{ $index }}"><strong>{{ $upload->getClientOriginalName() }}</strong>
<p class="ct-note">Лист {{ $mappings[$index]['sheet']??'—' }}; sku: {{ $mappings[$index]['code']??'не найден' }}; ID: {{ $mappings[$index]['old_id']??'—' }}; цена: {{ $mappings[$index]['price']??'—' }}</p>
<details><summary>Исправить распознавание колонок</summary><div class="ct-line">
@foreach(['sheet'=>'Лист','header'=>'Строка заголовка','code'=>'Колонка sku','old_id'=>'ID OpenCart','price'=>'Цена','image'=>'Изображение','categories'=>'Категории'] as $field=>$label)<label>{{ $label }}<input class="ct-input" wire:model="mappings.{{ $index }}.{{ $field }}"></label>@endforeach
</div></details></div>
@endforeach
@foreach($errors->all() as $error)<p role="alert">{{ $error }}</p>@endforeach
<div class="ct-line"><x-filament::button type="submit" wire:loading.attr="disabled" wire:target="uploads,createRun">{{ $analysisOnly ? 'Запустить проверку' : 'Запустить перенос товаров' }}</x-filament::button><label><input type="checkbox" wire:model.live="analysisOnly"> Только проверить, без изменений</label></div>
</form>
</section>
<section class="ct-card">
<h2>Доступ к API остатков</h2>
@if(!$apiConfigured)<p>Чтение каталога API выполняется без токена. Ошибки доступа отображаются в журнале товара.</p>@else<p>Токен сохранён. Ошибки доступа отображаются в журнале товара.</p>@endif
@if($this->canApply())<form wire:submit="saveApiToken" class="ct-line"><input type="password" class="ct-input" wire:model="apiToken" placeholder="Токен api.svetofor-mebel.ru" aria-label="Токен API" autocomplete="new-password"><x-filament::button type="submit" color="gray">Сохранить токен</x-filament::button></form><p class="ct-note">Токен хранится зашифрованным и не выводится в журнал. В чат его присылать не нужно.</p>@endif
@if($notice)<p role="status">{{ $notice }}</p>@endif
</section>
<div class="ct-line">@foreach($runs as $previous)<x-filament::button color="gray" wire:click="selectRun({{ $previous->id }})">{{ $previous->apply_enabled?'Перенос':'Проверка' }} №{{ $previous->id }}</x-filament::button>@endforeach</div>
<livewire:catalog-transfer-progress :run-id="$runId" :key="'transfer-progress-'.($runId ?? 'none')" />
<div class="ct-wrap">
<section class="ct-card">
<h2>3. Товары и замечания</h2>
<div class="ct-line"><input class="ct-input" wire:model.live.debounce.500ms="searchCode" placeholder="Поиск по коду 1С"><select class="ct-input" wire:model.live="statusFilter" aria-label="Статус результата"><option value="work">Товары с кодом sku</option><option value="">Все строки</option>@foreach(['pending'=>'В работе','applied'=>'Сохранено и дополнено','applied_waiting'=>'Сохранено, ожидает API','conflict'=>'Конфликты','error'=>'Ошибки','skipped'=>'Пропущено без sku'] as $key=>$label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></div>
<div class="ct-table-scroll"><table class="ct-table"><thead><tr><th>Код 1С</th><th>Товар на новом сайте</th><th>Цена таблицы</th><th>Состояние</th></tr></thead><tbody>
@forelse($rows as $row)
@php($product=$productsById->get($row->applied_product_id))
<tr wire:key="row-{{ $row->id }}"><td>{{ $row->code?:'—' }}<div class="ct-note">Строка {{ $row->row_number }}</div></td><td>
@if($product)<a href="{{ \App\Filament\Resources\Products\ProductResource::getUrl('edit',['record'=>$product->id]) }}">{{ $product->name }}</a><div class="ct-note">ID {{ $product->id }} · {{ $row->product_created?'Создан':'Обновлён' }}</div>@else{{ json_decode($row->source??'{}',true)['product_data']['name']??'Не сохранён' }}@endif
</td><td>{{ $row->price!==null?number_format((float)$row->price,0,',',' ').' ₽':'—' }}</td><td>{{ $row->message }}
@if($row->assets_result)@php($asset=json_decode($row->assets_result,true))<details><summary>Галерея: обработано {{ count($asset['completed']??[]) }} / {{ count($asset['images']??[]) }} файлов; замечаний {{ count($asset['warnings']??[]) }}</summary><p>{{ $row->assets_done ? 'Обработка завершена' : 'Обработка ещё идёт' }}. Завершение с замечаниями не означает, что все изображения загружены.</p>@foreach($asset['warnings']??[] as $warning)<p>{{ $warning }}</p>@endforeach</details>@endif
<details><summary>Источник и подробности</summary><p>{{ $row->file_name }}, строка {{ $row->row_number }}</p><pre>{{ json_encode(json_decode($row->source??'{}'),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) }}</pre></details>
@php($api=json_decode($row->result??'{}',true)['api']??null)
<details><summary>Данные API: {{ $row->api_applied_at ? 'сохранены в карточке' : (($api['status']??'')==='found' ? 'получены, не сохранены' : 'не получены') }}</summary>
@if(($api['status']??'')==='found')
<p>Код 1С: {{ $api['external_id']??$row->code }}. {{ $row->api_applied_at ? 'Остатки и характеристики сохранены: '.$row->api_applied_at : 'Предварительный просмотр — карточка не изменена.' }}</p>
<p>Производитель из таблицы: {{ json_decode($row->source??'{}',true)['product_data']['manufacturer']??'не указан' }}. Заполненные поля таблицы имеют приоритет над API.</p>
<h4>Склады и остатки ({{ count($api['stocks']??[]) }})</h4>
@forelse($api['stocks']??[] as $stock)<p>{{ $stock['stockName']??$stock['warehouseName']??$stock['name']??'Склад' }} · ID {{ $stock['stockId']??$stock['stock_id']??$stock['warehouseId']??$stock['warehouse_id']??'—' }} · {{ $stock['count']??$stock['quantity']??$stock['stock']??'—' }} шт.</p>@empty<p>API вернул пустой список складских остатков.</p>@endforelse
<h4>Характеристики ({{ count($api['characteristics']??[]) }})</h4>
@forelse($api['characteristics']??[] as $attribute)<p>{{ $attribute['name']??'Характеристика' }}: {{ is_array($attribute['values']??null) ? implode(', ',array_filter($attribute['values'], 'is_scalar')) : ($attribute['values']??'—') }}</p>@empty<p>API вернул пустой список характеристик.</p>@endforelse
<details><summary>Полный ответ API</summary><pre>{{ json_encode($api,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) }}</pre></details>
@else<p>Нет подтверждённого ответа API. {{ $row->message }}</p>@endif
</details>
@php($report=json_decode($row->result??'{}',true))
@php($assetReport=json_decode($row->assets_result??'{}',true))
<details><summary>Категории со старого сайта и результат сопоставления</summary>
@if(!empty($assetReport['category_report']))
@foreach($assetReport['category_report'] as $category)<p>Старый сайт: {{ implode(' → ',array_column($category['old_trail']??[], 'name')) ?: 'названия не получены' }} (путь {{ $category['old_path'] }}).</p><p>Новый сайт: {{ implode('; ',array_map(fn($match)=>implode(' → ',$match['trail']??[$match['name']]),$category['matches']??[])) ?: 'точное совпадение не найдено' }}. {{ $category['assigned'] ? 'Назначена товару.' : 'Не назначена — требуется проверка.' }}</p>@endforeach
@elseif(!empty($report['category_trails']))
@foreach($report['category_trails'] as $trail)<p>Старый сайт: {{ implode(' → ',array_column($trail,'name')) }}.</p>@endforeach
<p>Совпадения на новом сайте: {{ implode('; ',array_map(fn($match)=>implode(' → ',$match['trail']??[$match['name']]),$report['category_candidates']??[])) ?: 'не найдены' }}. Только проверка — категории не назначены.</p>
@else<p>Нет сохранённого отчёта с названиями категорий. Это не подтверждение успешного сопоставления.</p>@endif
@foreach($assetReport['warnings']??[] as $warning)@if(str_contains($warning,'Категория'))<p>{{ $warning }}</p>@endif @endforeach
</details>
</td></tr>
@empty<tr><td colspan="4">Нет строк для выбранного фильтра.</td></tr>@endforelse
</tbody></table></div>
<nav class="ct-line" aria-label="Страницы результатов"><x-filament::button color="gray" wire:click="previousPage" :disabled="$rows->onFirstPage()">Назад</x-filament::button><span>Страница {{ $rows->currentPage() }} из {{ $rows->lastPage() }} · {{ $rows->total() }} строк</span><x-filament::button color="gray" wire:click="nextPage" :disabled="!$rows->hasMorePages()">Далее</x-filament::button></nav>
<details><summary>Журнал обработки</summary>@foreach($events as $event)<p class="ct-note">{{ $event->created_at }} · {{ $event->code }} · {{ $event->message }}</p>@endforeach</details>
</section>
</div>
</div>
</x-filament-panels::page>
