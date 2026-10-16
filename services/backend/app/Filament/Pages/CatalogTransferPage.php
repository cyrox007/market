<?php

namespace App\Filament\Pages;

use App\Services\Catalog\Transfer\CatalogTransfer;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use UnitEnum;

class CatalogTransferPage extends Page
{
    use WithFileUploads, WithPagination;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentArrowDown;

    protected static ?string $navigationLabel = 'Перенос каталога';

    protected static ?string $title = 'Перенос товаров из OpenCart';

    protected static string|UnitEnum|null $navigationGroup = 'Интеграции';

    protected static ?string $slug = 'catalog-transfer';

    protected string $view = 'filament.pages.catalog-transfer';

    public array $uploads = [];

    public array $mappings = [];

    #[Locked]
    public ?int $runId = null;

    public string $searchCode = '';

    public string $statusFilter = 'work';

    public string $apiToken = '';

    public bool $analysisOnly = false;

    public string $notice = '';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('viewAny catalog_sync');
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->runId = DB::table('catalog_transfer_runs')->latest('id')->value('id');
    }

    public function updatedUploads(): void
    {
        foreach ($this->uploads as $index => $upload) {
            if (isset($this->mappings[$index])) {
                continue;
            }
            try {
                $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($upload->getRealPath());
                $names = $reader->listWorksheetNames($upload->getRealPath());
                $sheet = in_array('Products', $names, true) ? 'Products' : $names[0];
                $reader->setLoadSheetsOnly($sheet);
                $reader->setReadFilter(new class implements \PhpOffice\PhpSpreadsheet\Reader\IReadFilter
                {
                    public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
                    {
                        return $row === 1;
                    }
                });
                $book = $reader->load($upload->getRealPath());
                $headers = [];
                foreach ($book->getSheetByName($sheet)->getRowIterator(1, 1) as $row) {
                    foreach ($row->getCellIterator() as $cell) {
                        $headers[strtolower(trim((string) $cell->getValue()))] = $cell->getColumn();
                    }
                }
                $this->mappings[$index] = ['sheet' => $sheet, 'header' => 1, 'code' => $headers['sku'] ?? '', 'old_id' => $headers['product_id'] ?? '', 'price' => $headers['price'] ?? '', 'image' => $headers['image_name'] ?? $headers['image'] ?? '', 'categories' => $headers['categories'] ?? ''];
                $book->disconnectWorksheets();
            } catch (\Throwable) {
                $this->mappings[$index] = ['sheet' => '', 'header' => 1, 'code' => '', 'old_id' => '', 'price' => '', 'image' => '', 'categories' => ''];
                $this->addError('uploads', 'Не удалось распознать файл. Проверьте XLS/XLSX и заголовки.');
            }
        }
    }

    public function createRun(): void
    {
        abort_unless(static::canAccess(), 403);
        if (! $this->analysisOnly) {
            abort_unless($this->canApply(), 403);
        }
        $this->validate([
            'uploads' => 'required|array|min:1|max:12',
            'uploads.*' => 'required|file|mimes:xls,xlsx|max:12288',
            'mappings' => 'required|array',
            'mappings.*.sheet' => 'nullable|string|max:100',
            'mappings.*.header' => 'required|integer|min:1|max:100',
            'mappings.*.code' => 'required|regex:/^[A-Z]{1,3}$/',
            'mappings.*.old_id' => 'nullable|regex:/^[A-Z]{1,3}$/',
            'mappings.*.price' => 'nullable|regex:/^[A-Z]{1,3}$/',
            'mappings.*.image' => 'nullable|regex:/^[A-Z]{1,3}$/',
            'mappings.*.categories' => 'nullable|regex:/^[A-Z]{1,3}$/',
        ]);
        foreach (array_keys($this->uploads) as $index) {
            if (! isset($this->mappings[$index]['code'])) {
                $this->addError('uploads', 'Укажите колонки для каждого файла.');

                return;
            }
        }
        $this->runId = DB::transaction(function (): int {
            $id = DB::table('catalog_transfer_runs')->insertGetId([
                'apply_enabled' => ! $this->analysisOnly, 'status' => 'draft', 'message' => 'Файлы сохранены.', 'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ($this->uploads as $index => $upload) {
                DB::table('catalog_transfer_files')->insert([
                    'run_id' => $id, 'name' => $upload->getClientOriginalName(),
                    'path' => $upload->store('catalog-transfer/'.$id, 'local'),
                    'mapping' => json_encode($this->mappings[$index], JSON_THROW_ON_ERROR),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }

            return $id;
        });
        $this->uploads = [];
        $this->mappings = [];
        $this->resetPage();
        $this->start();
    }

    public function saveApiToken(): void
    {
        abort_unless($this->canApply(), 403);
        app(\App\Services\Catalog\Transfer\TransferApiSettings::class)->saveToken(trim($this->apiToken));
        $this->apiToken = '';
        $this->notice = 'Токен сохранён в зашифрованном виде. Нажмите «Повторить дополнение из источников» для ожидающих товаров.';
    }

    #[\Livewire\Attributes\On('catalog-transfer-selected')]
    public function selectRun(int $id): void
    {
        abort_unless(static::canAccess(), 403);
        abort_unless(DB::table('catalog_transfer_runs')->where('id', $id)->exists(), 404);
        $this->runId = $id;
        $this->resetPage();
    }

    public function start(): void
    {
        abort_unless(static::canAccess(), 403);
        if (DB::table('catalog_transfer_runs')->where('id', $this->runId)->value('apply_enabled')) {
            abort_unless($this->canApply(), 403);
        }
        $started = DB::transaction(function (): bool {
            $run = DB::table('catalog_transfer_runs')->where('id', $this->runId)->lockForUpdate()->first();
            if (! $run || ! in_array($run->status, ['draft', 'paused', 'running'])) {
                return false;
            }
            if ($run->status === 'running' && \Illuminate\Support\Carbon::parse($run->heartbeat_at ?? $run->updated_at)->gt(now()->subMinutes(3))) {
                return false;
            }
            DB::table('catalog_transfer_runs')->where('id', $this->runId)->update(['status' => 'running', 'heartbeat_at' => null, 'message' => 'Ожидание фонового обработчика.', 'updated_at' => now()]);

            return true;
        });
        if ($started) {
            app(CatalogTransfer::class)->enqueue($this->runId);
            app(CatalogTransfer::class)->log($this->runId, 'Анализ поставлен в очередь.');
        }
    }

    public function updatedSearchCode(): void
    {
        $this->resetPage();
    }

    public function retryErrors(): void
    {
        abort_unless(static::canAccess(), 403);
        $run = DB::table('catalog_transfer_runs')->find($this->runId);
        if (! $run || ! in_array($run->status, ['review', 'review_errors', 'paused'])) {
            return;
        }
        if ($run->apply_enabled) {
            abort_unless($this->canApply(), 403);
        }
        $changed = DB::table('catalog_transfer_rows')->where('run_id', $this->runId)
            ->where(fn ($q) => $q->where('status', 'applied_waiting')->orWhere(fn ($q) => $q->where('status', 'error')->where('attempts', '>', 0))
                ->when(! $run->apply_enabled, fn ($q) => $q->orWhereIn('status', ['matched', 'unmatched'])))
            ->update(['status' => 'pending', 'attempts' => 0, 'message' => 'Запрошена повторная проверка источника.', 'updated_at' => now()]);
        if ($changed > 0) {
            DB::table('catalog_transfer_runs')->where('id', $this->runId)->update(['status' => 'paused', 'updated_at' => now()]);
            $this->start();
        }
    }

    public function canApply(): bool
    {
        return app()->environment(['local', 'testing']) && static::canAccess()
            && auth()->user()->can('create products') && auth()->user()->can('update products');
    }

    public function applyRun(): void
    {
        abort_unless($this->canApply(), 403);
        $original = DB::table('catalog_transfer_runs')->find($this->runId);
        if (! $original || $original->apply_enabled || ! in_array($original->status, ['review', 'review_errors'])) {
            return;
        }
        $this->runId = DB::transaction(function () use ($original): int {
            $id = DB::table('catalog_transfer_runs')->insertGetId(['apply_enabled' => true, 'status' => 'draft', 'created_at' => now(), 'updated_at' => now()]);
            foreach (DB::table('catalog_transfer_files')->where('run_id', $original->id)->get() as $file) {
                DB::table('catalog_transfer_files')->insert(['run_id' => $id, 'name' => $file->name, 'path' => $file->path, 'mapping' => $file->mapping, 'created_at' => now(), 'updated_at' => now()]);
            }

            return $id;
        });
        $this->start();
    }

    public function continueAssets(): void
    {
        abort_unless($this->canApply(), 403);
        $run = DB::table('catalog_transfer_runs')->find($this->runId);
        if (! $run || ! $run->apply_enabled || ! in_array($run->status, ['review', 'review_errors', 'paused'])) {
            return;
        }
        DB::table('catalog_transfer_runs')->where('id', $this->runId)->update(['status' => 'paused', 'updated_at' => now()]);
        $this->start();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function pause(): void
    {
        abort_unless(static::canAccess(), 403);
        DB::table('catalog_transfer_runs')->where('id', $this->runId)->where('status', 'running')->update([
            'status' => 'paused', 'message' => 'Пауза. Текущая небольшая порция будет сохранена.', 'updated_at' => now(),
        ]);
        if ($this->runId !== null) {
            app(CatalogTransfer::class)->log($this->runId, 'Запрошена пауза.');
        }
    }

    protected function getViewData(): array
    {
        abort_unless(static::canAccess(), 403);
        $query = DB::table('catalog_transfer_rows')->where('catalog_transfer_rows.run_id', $this->runId);
        $counts = (clone $query)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $productIds = (clone $query)->whereNotNull('applied_product_id')->pluck('applied_product_id');

        return [
            'runs' => DB::table('catalog_transfer_runs')->latest('id')->limit(20)->get(),
            'run' => DB::table('catalog_transfer_runs')->find($this->runId),
            'files' => DB::table('catalog_transfer_files')->where('run_id', $this->runId)->get(),
            'counts' => $counts,
            'readTotal' => $counts->sum(),
            'codeTotal' => (clone $query)->where('code', '!=', '')->count(),
            'checkedTotal' => (clone $query)->where('code', '!=', '')->where('status', '!=', 'pending')->count(),
            'pendingTotal' => (clone $query)->where('status', 'pending')->count(),
            'matchedTotal' => $counts['matched'] ?? 0,
            'unmatchedTotal' => $counts['unmatched'] ?? 0,
            'apiFoundTotal' => (clone $query)->where('result->api->status', 'found')->count(),
            'apiMissingTotal' => (clone $query)->where('result->api->status', 'not_found')->count(),
            'apiRequestTotal' => DB::table('catalog_transfer_events')->where('run_id', $this->runId)->where('message', 'like', 'API запрос:%')->count(),
            'apiResponseTotal' => DB::table('catalog_transfer_events')->where('run_id', $this->runId)->where('message', 'like', 'API ответ:%')->count(),
            'apiLastResponse' => DB::table('catalog_transfer_events')->where('run_id', $this->runId)->where('message', 'like', 'API ответ:%')->latest('id')->first(),
            'appliedTotal' => (clone $query)->whereNotNull('table_applied_at')->distinct()->count('applied_product_id'),
            'createdTotal' => (clone $query)->where('product_created', true)->count(),
            'assetsTotal' => (clone $query)->whereNotNull('table_applied_at')->where('assets_done', true)->count(),
            'apiTotal' => (clone $query)->whereNotNull('api_applied_at')->count(),
            'eligibleTotal' => (clone $query)->where('code', '!=', '')->whereNotIn('status', ['conflict', 'skipped'])->count(),
            'productsById' => \App\Models\Product\Product::whereIn('id', $productIds)->get(['id', 'name', 'price', 'slug'])->keyBy('id'),
            'apiConfigured' => filled(app(\App\Services\Catalog\Transfer\TransferApiSettings::class)->config()['api_key'] ?? ''),
            'currentAsset' => (clone $query)->whereNotNull('table_applied_at')->where('assets_done', false)->orderBy('id')->first(),
            'currentRow' => (clone $query)->where('catalog_transfer_rows.status', 'pending')->orderBy('id')->first(),
            'events' => DB::table('catalog_transfer_events')->where('run_id', $this->runId)
                ->when($this->searchCode !== '', fn ($q) => $q->where('code', 'like', '%'.$this->searchCode.'%'))
                ->when(in_array($this->statusFilter, ['error', 'conflict']), fn ($q) => $q->where('level', 'error'))
                ->latest('id')->limit(100)->get(),
            'rows' => $query->join('catalog_transfer_files', 'file_id', '=', 'catalog_transfer_files.id')
                ->select('catalog_transfer_rows.*', 'catalog_transfer_files.name as file_name')
                ->when($this->searchCode !== '', fn ($q) => $q->where('code', 'like', '%'.$this->searchCode.'%'))
                ->when($this->statusFilter === 'work', fn ($q) => $q->where('catalog_transfer_rows.status', '!=', 'skipped'))
                ->when(! in_array($this->statusFilter, ['', 'work']), fn ($q) => $q->where('catalog_transfer_rows.status', $this->statusFilter))
                ->orderByDesc('catalog_transfer_rows.id')->paginate(25),
        ];
    }
}
