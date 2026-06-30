<?php

namespace App\Console\Commands;

use App\Models\Product\Product;
use Illuminate\Console\Command;

/**
 * Сведение с отдельным файлом: обновить у импортированных товаров поле «Внешний ID 1С» (external_id)
 * с OpenCart product_id на uuid из файла (бэкап oc_product, CSV или SQL).
 *
 * Текущий импорт оставляет external_id = product_id. Этот шаг подменяет external_id на uuid
 * для сопоставления с 1С/другими системами, где идентификатор — uuid.
 */
class ApplyOpenCartUuidMappingCommand extends Command
{
    protected $signature = 'catalog:apply-opencart-uuid-mapping
                            {file : Путь к файлу с маппингом product_id → uuid (CSV или SQL бэкап oc_product)}
                            {--dry-run : Только показать, что будет обновлено}';

    protected $description = 'Обновить external_id импортированных товаров OpenCart: product_id → uuid из отдельного файла';

    public function handle(): int
    {
        $path = (string) $this->argument('file');
        $path = trim($path);
        if ($path === '') {
            $this->error('Укажите путь к файлу (CSV или SQL).');
            return self::FAILURE;
        }
        $path = $this->resolvePath($path);
        if (! is_file($path) || ! is_readable($path)) {
            $this->error('Файл не найден или недоступен: ' . $path);
            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        $mapping = $ext === 'sql'
            ? $this->parseSqlBackup($path)
            : $this->parseCsv($path);

        if (empty($mapping)) {
            $this->warn('Маппинг product_id → uuid пуст. Проверьте формат файла.');
            return self::SUCCESS;
        }

        $this->info('Найдено записей в файле: ' . count($mapping));
        if ($dryRun) {
            $this->line('Режим --dry-run: обновление не выполняется.');
        }

        $updated = 0;
        $notFound = 0;

        foreach ($mapping as $productId => $uuid) {
            $productId = (string) $productId;
            $uuid = trim((string) $uuid);
            if ($uuid === '') {
                continue;
            }
            $product = Product::query()
                ->whereNull('parent_product_id')
                ->where('external_id', $productId)
                ->first();
            if ($product === null) {
                $notFound++;
                continue;
            }
            if ($dryRun) {
                $this->line("  product_id={$productId} → uuid={$uuid} (id={$product->id})");
                $updated++;
                continue;
            }
            $product->update(['external_id' => $uuid]);
            $updated++;
        }

        $this->info('Обновлено товаров: ' . $updated);
        if ($notFound > 0) {
            $this->warn('Не найдено по external_id (product_id): ' . $notFound);
        }

        return self::SUCCESS;
    }

    /**
     * CSV: строки product_id,uuid или заголовок product_id,uuid.
     *
     * @return array<string, string> product_id => uuid
     */
    private function parseCsv(string $path): array
    {
        $mapping = [];
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return [];
        }
        $header = fgetcsv($handle, 0, ',');
        if ($header === false) {
            fclose($handle);
            return [];
        }
        $header = array_map('trim', array_map('strtolower', $header));
        $idxId = array_search('product_id', $header, true);
        $idxUuid = array_search('uuid', $header, true);
        if ($idxId === false || $idxUuid === false) {
            $idxId = 0;
            $idxUuid = 1;
        }
        while (($row = fgetcsv($handle, 0, ',')) !== false) {
            $id = isset($row[$idxId]) ? trim((string) $row[$idxId]) : '';
            $uuid = isset($row[$idxUuid]) ? trim((string) $row[$idxUuid]) : '';
            if ($id !== '' && $uuid !== '') {
                $mapping[$id] = $uuid;
            }
        }
        fclose($handle);

        return $mapping;
    }

    /**
     * Парсит SQL бэкап oc_product: из INSERT извлекает product_id (первое значение) и uuid (формат UUID перед is_synchronization).
     *
     * @return array<string, string> product_id => uuid
     */
    private function parseSqlBackup(string $path): array
    {
        $sql = file_get_contents($path);
        if ($sql === false) {
            return [];
        }
        $mapping = [];
        foreach (explode("\n", $sql) as $line) {
            if (preg_match("/\(\s*'(\d+)'.*'([a-f0-9\-]{36})'\s*,\s*''\s*\)/", $line, $m)) {
                $mapping[$m[1]] = $m[2];
            }
        }

        return $mapping;
    }

    private function resolvePath(string $path): string
    {
        if ($path === '' || $path[0] === '/' || (strlen($path) >= 2 && $path[1] === ':')) {
            return $path;
        }

        return base_path($path);
    }
}
