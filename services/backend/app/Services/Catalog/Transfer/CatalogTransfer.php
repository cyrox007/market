<?php

namespace App\Services\Catalog\Transfer;

use App\Jobs\AnalyzeCatalogTransfer;
use App\Models\Product\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

class CatalogTransfer
{
    public function log(int $runId, string $message, ?string $code = null, string $level = 'info'): void
    {
        DB::table('catalog_transfer_events')->insert([
            'run_id' => $runId, 'code' => $code, 'level' => $level, 'message' => $message, 'created_at' => now(),
        ]);
    }

    public function enqueue(int $runId, int $delay = 0): void
    {
        AnalyzeCatalogTransfer::dispatch($runId)->onConnection('database')->onQueue('catalog-transfer')->delay($delay);
    }

    /** Each invocation commits a small, repeatable checkpoint. Analysis remains read-only. */
    public function step(int $runId): int
    {
        $run = DB::table('catalog_transfer_runs')->find($runId);
        if (! $run || $run->status !== 'running') {
            return -1;
        }
        DB::table('catalog_transfer_runs')->where('id', $runId)->update(['heartbeat_at' => now(), 'updated_at' => now()]);
        $file = DB::table('catalog_transfer_files')->where('run_id', $runId)->where('status', 'pending')->orderBy('id')->first();
        if ($file) {
            try {
                $this->readChunk($file);
            } catch (\Throwable $exception) {
                report($exception);
                DB::table('catalog_transfer_files')->where('id', $file->id)->update([
                    'status' => 'error', 'message' => 'Не удалось прочитать файл. Проверьте лист и колонки.', 'updated_at' => now(),
                ]);
                $this->log($runId, $file->name.': ошибка чтения файла.', level: 'error');
            }

            return 1;
        }
        if ($run->apply_enabled) {
            $tableRow = DB::table('catalog_transfer_rows')->where('run_id', $runId)->where('status', 'pending')->whereNull('table_applied_at')->orderBy('id')->first();
            if ($tableRow) {
                try {
                    app(TransferProductWriter::class)->applyTable($tableRow->id);
                } catch (\DomainException $exception) {
                    $this->finish($tableRow->id, 'conflict', $exception->getMessage());
                } catch (\Throwable $exception) {
                    report($exception);
                    $this->finish($tableRow->id, 'error', 'Не удалось сохранить данные таблицы; строка не применена.');
                }

                return 0;
            }
        }
        $row = DB::table('catalog_transfer_rows')->where('run_id', $runId)->where('status', 'pending')->orderBy('id')->first();
        if (! $row) {
            if ($run->apply_enabled) {
                $assetsDelay = app(TransferAssets::class)->step($runId);
                if ($assetsDelay >= 0) {
                    return $assetsDelay;
                }
            }
            $hasErrors = DB::table('catalog_transfer_files')->where('run_id', $runId)->where('status', 'error')->exists()
                || DB::table('catalog_transfer_rows')->where('run_id', $runId)->whereIn('status', ['error', 'conflict', 'applied_waiting'])->exists()
                || DB::table('catalog_transfer_rows')->where('run_id', $runId)->whereJsonLength('assets_result->warnings', '>', 0)->exists();
            DB::table('catalog_transfer_runs')->where('id', $runId)->where('status', 'running')->update([
                'status' => $hasErrors ? 'review_errors' : 'review', 'message' => $run->apply_enabled ? 'Данные таблиц применены. Проверьте ожидающие дополнения и конфликты.' : 'Анализ завершён. Товары не изменены.', 'updated_at' => now(),
            ]);

            return -1;
        }
        try {
            $sources = app(TransferSources::class);
            TransferSources::track($runId, $row->code);
            DB::table('catalog_transfer_runs')->where('id', $runId)->update(['message' => ($run->apply_enabled ? 'Получение остатков и характеристик из API: ' : 'Проверка источников для кода 1С: ').$row->code]);
            if ($run->apply_enabled) {
                $data = $sources->enrichment($row->code);
                if ($data['status'] !== 'found') {
                    $this->finish($row->id, 'applied_waiting', 'Товар сохранён из таблицы. Дополнение из API ожидает: '.$data['status'].'.', ['api' => $data]);

                    return 0;
                }
                app(TransferProductWriter::class)->applyApi($row->id, $data);
                $this->finish($row->id, 'applied', 'Данные таблицы сохранены; характеристики и складские остатки дополнены из API.', ['api' => $data]);

                return 1;
            }
            $siblings = DB::table('catalog_transfer_rows')->where('catalog_transfer_rows.run_id', $runId)->where('code', $row->code);
            $conflicts = (clone $siblings)->distinct()->count('old_id') > 1
                || (clone $siblings)->distinct()->count('price') > 1;
            if ($conflicts) {
                $this->finish($row->id, 'conflict', 'Для кода 1С указаны разные цены или ID старого товара.');

                return 1;
            }
            $products = Product::query()->where(fn ($query) => $query->where('external_id', $row->code)->orWhere('sku', $row->code))->get(['id', 'name', 'price', 'parent_product_id']);
            if ($products->count() > 1) {
                $this->finish($row->id, 'conflict', 'Код 1С неоднозначен в новом каталоге.');

                return 1;
            }
            $oldId = $row->old_id ?? (clone $siblings)->whereNotNull('old_id')->value('old_id');
            $price = $row->price ?? (clone $siblings)->whereNotNull('price')->value('price');
            $sourceRows = (clone $siblings)->whereNotNull('source')->pluck('source');
            $originals = [];
            $categoryPaths = [];
            foreach ($sourceRows as $sourceRow) {
                $originals = array_merge($originals, json_decode($sourceRow, true)['images'] ?? []);
                $categoryPaths = array_merge($categoryPaths, json_decode($sourceRow, true)['category_paths'] ?? []);
            }
            $categoryPaths = array_values(array_unique($categoryPaths));
            $card = null;
            $candidates = [];
            $trails = [];
            if ($oldId) {
                foreach ($categoryPaths ?: [null] as $categoryPath) {
                    $parsed = $sources->card((int) $oldId, $categoryPath);
                    $card ??= $parsed;
                    if ($parsed['category_trail']) {
                        $trails[] = $parsed['category_trail'];
                    }
                    $candidates = array_merge($candidates, app(TransferCategoryMatcher::class)->candidates($parsed['category_trail']));
                }
            }
            $api = $sources->enrichment($row->code);
            $this->finish($row->id, $products->isEmpty() ? 'unmatched' : 'matched',
                $products->isEmpty() ? 'Данные источников проверены. На новом сайте код 1С не найден; создание отключено.' : 'Сопоставлено по коду 1С. Требуется проверка, изменения не применены.', [
                    'product' => $products->first()?->toArray(), 'proposed_price' => $price,
                    'old_product_id' => $oldId, 'old_card' => $card, 'api' => $api,
                    'category_action' => 'manual_review', 'image_action' => 'preview_only',
                    'category_candidates' => $candidates, 'category_trails' => $trails,
                    'original_images_from_files' => array_values(array_unique($originals)),
                    'source_rows' => (clone $siblings)->join('catalog_transfer_files', 'file_id', '=', 'catalog_transfer_files.id')
                        ->get(['catalog_transfer_files.name', 'row_number', 'source'])->map(fn ($item) => [
                            'file' => $item->name, 'row' => $item->row_number, 'sheet' => json_decode($item->source ?? '{}', true)['sheet'] ?? null,
                        ])->all(),
                ]);
        } catch (TransferRetry $exception) {
            if (! $exception->countAttempt) {
                return $exception->delay;
            }
            $attempts = $row->attempts + 1;
            DB::table('catalog_transfer_rows')->where('id', $row->id)->update([
                'attempts' => $attempts, 'message' => 'Ожидание источника; повтор '.$attempts.' из 5.', 'updated_at' => now(),
            ]);
            $this->log($runId, 'Ожидание источника: '.$exception->delay.' сек., попытка '.$attempts.'/5.', $row->code, 'warning');
            if ($attempts >= 5) {
                $this->finish($row->id, $run->apply_enabled ? 'applied_waiting' : 'error', $run->apply_enabled ? 'Товар сохранён. Дополнение ожидает повтора после 5 неудачных попыток.' : 'Источник не ответил после 5 попыток.');

                return 1;
            }

            return $exception->delay;
        } catch (\Throwable $exception) {
            report($exception);
            $http = preg_match('/HTTP (\d{3})/', $exception->getMessage(), $match) ? ' HTTP '.$match[1].($match[1] === '401' ? ': API на хостинге требует авторизацию; локальное снятие проверки ещё не развёрнуто.' : '.') : '';
            $this->finish($row->id, $run->apply_enabled ? 'applied_waiting' : 'error', ($run->apply_enabled ? 'Товар сохранён из таблицы. Ошибка API; дополнение можно повторить.' : 'Ошибка получения данных источника. Подробности в серверном журнале.').$http);
        } finally {
            TransferSources::track(null, null);
        }

        return 1;
    }

    private function finish(int $id, string $status, string $message, ?array $result = null): void
    {
        DB::table('catalog_transfer_rows')->where('id', $id)->update([
            'status' => $status, 'message' => $message,
            'result' => $result === null ? null : json_encode($result, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 'updated_at' => now(),
        ]);
        $row = DB::table('catalog_transfer_rows')->find($id);
        $this->log($row->run_id, 'Строка '.$row->row_number.': '.$message, $row->code,
            in_array($status, ['error', 'conflict']) ? 'error' : 'info');
    }

    private function readChunk(object $file): void
    {
        $mapping = json_decode($file->mapping, true, 16, JSON_THROW_ON_ERROR);
        $path = Storage::disk('local')->path($file->path);
        $type = IOFactory::identify($path);
        if (! in_array($type, ['Xls', 'Xlsx'], true)) {
            throw new \RuntimeException('Требуется XLS или XLSX.');
        }
        if ($type === 'Xlsx') {
            $zip = new \ZipArchive;
            if ($zip->open($path) !== true) {
                throw new \RuntimeException('Повреждённый XLSX.');
            }
            try {
                $expanded = 0;
                for ($index = 0; $index < $zip->numFiles; $index++) {
                    $expanded += $zip->statIndex($index)['size'];
                    if ($expanded > 256 * 1024 * 1024) {
                        throw new \RuntimeException('Слишком большой распакованный XLSX.');
                    }
                }
            } finally {
                $zip->close();
            }
        }
        $reader = IOFactory::createReader($type);
        $sheets = $reader->listWorksheetInfo($path);
        $sheet = collect($sheets)->firstWhere('worksheetName', $mapping['sheet'])
            ?? (($mapping['sheet'] ?? '') === '' ? ($sheets[0] ?? null) : null);
        if (! $sheet || $sheet['totalRows'] > 100000) {
            throw new \RuntimeException('Лист не найден или превышает 100 000 строк.');
        }
        $start = max($file->cursor + 1, (int) $mapping['header'] + 1);
        $end = min($start + 99, $sheet['totalRows']);
        $reader->setLoadSheetsOnly($sheet['worksheetName']);
        // Keep formatting: numeric 1C codes with an explicit zero mask must retain leading zeros.
        $reader->setReadDataOnly(false);
        $reader->setReadFilter(new class($start, $end) implements IReadFilter
        {
            public function __construct(private int $start, private int $end) {}

            public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
            {
                return $row >= $this->start && $row <= $this->end;
            }
        });
        $book = $reader->load($path);
        try {
            $worksheet = $book->getSheetByName($sheet['worksheetName']);
            // Recognized OpenCart columns are preserved as data, not executed as instructions or formulas.
            $headers = [];
            $headerReader = IOFactory::createReader($type);
            $headerReader->setLoadSheetsOnly($sheet['worksheetName']);
            $headerReader->setReadFilter(new class((int) $mapping['header']) implements IReadFilter
            {
                public function __construct(private int $header) {}

                public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
                {
                    return $row === $this->header;
                }
            });
            $headerBook = $headerReader->load($path);
            foreach ($headerBook->getSheetByName($sheet['worksheetName'])->getRowIterator((int) $mapping['header'], (int) $mapping['header']) as $headerRow) {
                foreach ($headerRow->getCellIterator() as $cell) {
                    $key = preg_replace('/\(ru[-_]ru\)/', '', strtolower(trim((string) $cell->getValue())));
                    $headers[$key] = $cell->getColumn();
                }
            }
            $headerBook->disconnectWorksheets();
            $additionalImages = $this->additionalImages($path, $type, $sheets);
            DB::transaction(function () use ($file, $mapping, $start, $end, $worksheet, $sheet, $additionalImages, $headers): void {
                for ($number = $start; $number <= $end; $number++) {
                    $formula = false;
                    $read = function (string $field) use ($mapping, $worksheet, $number, &$formula): string {
                        $column = $mapping[$field] ?? '';
                        if ($column === '') {
                            return '';
                        }
                        $cell = $worksheet->getCell($column.$number);
                        if ($cell->getDataType() === 'f') {
                            $formula = true;

                            return '';
                        }

                        return trim((string) ($field === 'code' ? $cell->getFormattedValue() : $cell->getValue()));
                    };
                    $code = $read('code');
                    $productData = [];
                    foreach (['name', 'description', 'excerpt', 'manufacturer', 'model', 'status', 'meta_title', 'meta_description', 'width', 'height', 'length', 'length_unit'] as $field) {
                        if (isset($headers[$field])) {
                            $cell = $worksheet->getCell($headers[$field].$number);
                            if ($cell->getDataType() === 'f') {
                                $formula = true;
                            } elseif ($cell->getValue() !== null && trim((string) $cell->getValue()) !== '') {
                                $productData[$field] = trim((string) $cell->getValue());
                            }
                        }
                    }
                    $old = $read('old_id');
                    $priceText = str_replace(["\xc2\xa0", ' ', ','], ['', '', '.'], $read('price'));
                    $categoryPaths = array_values(array_filter(array_map('trim', preg_split('/[,;\r\n]+/', $read('categories'))), fn ($value) => $value !== '' && $value !== '0'));
                    $validCategories = count($categoryPaths) <= 20 && collect($categoryPaths)->every(fn ($path) => (bool) preg_match('/^\d+(?:_\d+)*$/', $path));
                    $images = [];
                    foreach (preg_split('/[;\r\n]+/', $read('image')) as $image) {
                        $safeImage = $this->originalImage($image);
                        if ($safeImage !== null) {
                            $images[] = $safeImage;
                        }
                    }
                    $images = array_values(array_unique(array_merge($images, $additionalImages[$old] ?? [])));
                    if ($code === '' && $old === '' && $priceText === '' && ! $formula) {
                        continue;
                    }
                    $valid = ! $formula && $validCategories && $code !== '' && mb_strlen($code) <= 255
                        && ($old === '' || (ctype_digit($old) && (int) $old > 0 && strlen($old) <= 10))
                        && ($priceText === '' || (preg_match('/^\d{1,12}(?:\.\d{1,2})?$/', $priceText)));
                    DB::table('catalog_transfer_rows')->insertOrIgnore([
                        'run_id' => $file->run_id, 'file_id' => $file->id, 'row_number' => $number,
                        'code' => mb_substr($code, 0, 255), 'old_id' => $valid && $old !== '' ? (int) $old : null,
                        'price' => $valid && $priceText !== '' ? $priceText : null,
                        'status' => $code === '' && ! $formula ? 'skipped' : ($valid ? 'pending' : 'error'),
                        'source' => json_encode(['sheet' => $sheet['worksheetName'], 'product_data' => $productData, 'images' => $images, 'category_paths' => $validCategories ? $categoryPaths : []], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                        'message' => $code === '' && ! $formula ? 'Пропущено: в sku нет кода 1С.' : ($formula ? 'Формулы не поддерживаются: сохраните значения.' : ($valid ? 'Прочитано с листа '.$sheet['worksheetName'] : 'Проверьте код 1С, ID и цену.')),
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
                DB::table('catalog_transfer_files')->where('id', $file->id)->update([
                    'cursor' => max($end, $file->cursor), 'total' => max(0, $sheet['totalRows'] - (int) $mapping['header']),
                    'status' => $end >= $sheet['totalRows'] ? 'read' : 'pending',
                    'message' => 'Лист: '.$sheet['worksheetName'], 'updated_at' => now(),
                ]);
                $this->log($file->run_id, $file->name.' / '.$sheet['worksheetName'].': прочитаны строки '.$start.'–'.$end.'.');
            });
        } finally {
            $book->disconnectWorksheets();
        }
    }

    private function originalImage(string $path): ?string
    {
        $path = trim($path);
        if (str_starts_with($path, 'catalog/')) {
            $path = 'https://'.OpenCartCardParser::HOST.'/image/'.implode('/', array_map('rawurlencode', explode('/', $path)));
        }

        return app(OpenCartCardParser::class)->imageUrl($path);
    }

    /** OpenCart exports may contain unrelated products on auxiliary sheets: join by product_id only. */
    private function additionalImages(string $path, string $type, array $sheets): array
    {
        $info = collect($sheets)->firstWhere('worksheetName', 'AdditionalImages');
        if (! $info) {
            return [];
        }
        if ($info['totalRows'] > 100000) {
            throw new \RuntimeException('Слишком большой лист дополнительных изображений.');
        }
        $reader = IOFactory::createReader($type);
        $reader->setLoadSheetsOnly('AdditionalImages')->setReadDataOnly(true);
        $book = $reader->load($path);
        try {
            $sheet = $book->getSheetByName('AdditionalImages');
            if ($sheet->getCell('A1')->getValue() !== 'product_id' || $sheet->getCell('B1')->getValue() !== 'image') {
                throw new \RuntimeException('Неожиданный формат AdditionalImages.');
            }
            $images = [];
            for ($row = 2; $row <= $info['totalRows']; $row++) {
                $id = (string) $sheet->getCell('A'.$row)->getValue();
                $cell = $sheet->getCell('B'.$row);
                if (! ctype_digit($id) || $cell->getDataType() === 'f') {
                    continue;
                }
                $url = $this->originalImage((string) $cell->getValue());
                if ($url !== null) {
                    $images[$id][] = $url;
                }
            }

            return $images;
        } finally {
            $book->disconnectWorksheets();
        }
    }
}
