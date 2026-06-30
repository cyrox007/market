<?php

namespace App\Console\Commands;

use App\Services\Catalog\AttributeParamsImportService;
use App\Services\Catalog\AttributeParamsXlsxReader;
use Illuminate\Console\Command;

class ImportAttributeParamsFromXlsxCommand extends Command
{
    protected $signature = 'catalog:import-attribute-params
                            {--path= : Путь к XLSX (по умолчанию storage/app/product_params.xlsx)}
                            {--apply : Выполнить импорт (по умолчанию только показать dry-run сводку)}';

    protected $description = 'Импортировать или проанализировать характеристики и их значения из product_params.xlsx';

    public function handle(AttributeParamsImportService $importService): int
    {
        $pathOption = $this->option('path');
        $path = null;
        if (is_string($pathOption) && $pathOption !== '') {
            $path = $this->resolvePath($pathOption);
        }

        $apply = (bool) $this->option('apply');

        $reader = $path !== null
            ? new AttributeParamsXlsxReader($path)
            : new AttributeParamsXlsxReader();

        $this->info('Чтение файла характеристик...');
        try {
            $attributes = $reader->read();
        } catch (\Throwable $e) {
            $this->error('Ошибка чтения XLSX: ' . $e->getMessage());

            return self::FAILURE;
        }

        if (empty($attributes)) {
            $this->warn('Атрибуты не найдены (заголовочные блоки не распознаны).');

            return self::SUCCESS;
        }

        $this->line('');
        $this->info('Найдены атрибуты (dry-run):');

        foreach ($attributes as $index => $attribute) {
            $values = $attribute['values'] ?? [];
            $categories = $attribute['categories'] ?? [];
            $sampleValues = array_slice($values, 0, 5);

            $this->line('');
            $this->line(sprintf(
                '[%d] %s (колонка %d)',
                $index + 1,
                $attribute['name'],
                $attribute['column']
            ));
            $this->line(sprintf('  Строки заголовка: %d–%d', $attribute['header_rows'][0], $attribute['header_rows'][1]));
            $this->line(sprintf('  Строки значений: %d–%d', $attribute['value_rows'][0], $attribute['value_rows'][1]));
            $this->line(sprintf('  Всего значений: %d', count($values)));
            if ($sampleValues !== []) {
                $this->line('  Примеры значений: ' . json_encode($sampleValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            }

            $categorySamples = array_slice($categories, 0, 5);
            if ($categorySamples !== []) {
                $this->line('  Примеры категорий (group/type/subtype):');
                foreach ($categorySamples as $cat) {
                    $this->line(sprintf(
                        '    - %s / %s / %s',
                        $cat['group'] ?? '-',
                        $cat['type'] ?? '-',
                        $cat['subtype'] ?? '-'
                    ));
                }
            }
        }

        if (! $apply) {
            $this->line('');
            $this->info('Режим dry-run: данные в БД не изменены.');
            $this->line('Для импорта запустите команду с флагом --apply.');

            return self::SUCCESS;
        }

        $this->line('');
        $this->info('Выполняется импорт атрибутов и значений в БД...');

        try {
            $result = $importService->import($path);
        } catch (\Throwable $e) {
            $this->error('Ошибка импорта: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->line('');
        $this->info('Импорт завершён:');
        $this->info('  Атрибуты: создано ' . $result['created_attributes'] . ', обновлено ' . $result['updated_attributes']);
        $this->info('  Значения: создано ' . $result['created_values'] . ', обновлено ' . $result['updated_values']);

        return self::SUCCESS;
    }

    private function resolvePath(string $file): string
    {
        if (str_starts_with($file, '/')) {
            return $file;
        }

        return base_path($file);
    }
}

