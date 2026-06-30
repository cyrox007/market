<?php

namespace App\Console\Commands;

use App\Services\Catalog\Integrations\OpenCartXlsxCatalogImport;
use Illuminate\Console\Command;

class ImportOpenCartFullCommand extends Command
{
    protected $signature = 'catalog:import-opencart-full
                            {--products= : Путь к XLSX товаров (обязательно)}
                            {--categories= : Путь к XLSX категорий (categories-2026-03-03.xlsx)}
                            {--attributes= : Путь к XLSX атрибутов (attributes-2026-03-03.xlsx)}
                            {--options= : Путь к XLSX опций (options-2026-03-03.xlsx)}
                            {--uuid-sql= : После импорта подставить uuid в «Внешний ID 1С» из SQL/CSV (бэкап oc_product)}
                            {--limit= : Ограничить кол-во товаров для теста}
                            {--no-images : Не скачивать изображения}';

    protected $description = 'Полный импорт OpenCart: категории, справочники атрибутов/опций, товары (все XLSX одной командой)';

    public function handle(): int
    {
        $productsPath = $this->option('products');
        if ($productsPath === null || trim((string) $productsPath) === '') {
            $this->error('Укажите --products=путь к XLSX товаров (обязательно).');
            return self::FAILURE;
        }
        $productsPath = $this->resolvePath(trim((string) $productsPath));
        if (! is_file($productsPath) || ! is_readable($productsPath)) {
            $this->error('Файл товаров не найден или недоступен: ' . $productsPath);
            return self::FAILURE;
        }

        $options = [
            'file' => $productsPath,
        ];

        $categoriesPath = $this->option('categories');
        if ($categoriesPath !== null && trim((string) $categoriesPath) !== '') {
            $resolved = $this->resolvePath(trim((string) $categoriesPath));
            if (is_file($resolved) && is_readable($resolved)) {
                $options['categories_file'] = $resolved;
                $this->line('Категории: ' . $resolved);
            } else {
                $this->warn('Файл категорий не найден: ' . $resolved);
            }
        }

        $attributesPath = $this->option('attributes');
        if ($attributesPath !== null && trim((string) $attributesPath) !== '') {
            $resolved = $this->resolvePath(trim((string) $attributesPath));
            if (is_file($resolved) && is_readable($resolved)) {
                $options['attributes_file'] = $resolved;
                $this->line('Атрибуты: ' . $resolved);
            } else {
                $this->warn('Файл атрибутов не найден: ' . $resolved);
            }
        }

        $optionsPath = $this->option('options');
        if ($optionsPath !== null && trim((string) $optionsPath) !== '') {
            $resolved = $this->resolvePath(trim((string) $optionsPath));
            if (is_file($resolved) && is_readable($resolved)) {
                $options['options_file'] = $resolved;
                $this->line('Опции: ' . $resolved);
            } else {
                $this->warn('Файл опций не найден: ' . $resolved);
            }
        }

        $limit = $this->option('limit');
        if ($limit !== null && $limit !== '') {
            $options['limit'] = (int) $limit;
        }
        if ((bool) $this->option('no-images')) {
            $options['no_images'] = true;
        }

        $this->info('Товары: ' . $productsPath);
        $this->line('');

        $importer = app(OpenCartXlsxCatalogImport::class);
        $result = $importer->import($options);

        if (! empty($result->errors)) {
            foreach ($result->errors as $err) {
                $this->error($err);
            }
            return self::FAILURE;
        }

        $this->info('Категории: создано ' . $result->createdCategories . ', обновлено ' . $result->updatedCategories);
        $this->info('Товары: создано ' . $result->createdProducts . ', обновлено ' . $result->updatedProducts);
        $this->info('Время: ' . $result->durationSeconds . ' с');

        $uuidPath = $this->option('uuid-sql');
        if ($uuidPath !== null && trim((string) $uuidPath) !== '') {
            $uuidPath = $this->resolvePath(trim((string) $uuidPath));
            if (is_file($uuidPath) && is_readable($uuidPath)) {
                $this->line('');
                $this->info('Сведение uuid из файла: ' . $uuidPath);
                $code = $this->call('catalog:apply-opencart-uuid-mapping', [
                    'file' => $uuidPath,
                ]);
                if ($code !== 0) {
                    return $code;
                }
            } else {
                $this->warn('Файл uuid (--uuid-sql) не найден или недоступен: ' . $uuidPath);
            }
        }

        return self::SUCCESS;
    }

    private function resolvePath(string $path): string
    {
        if (str_starts_with($path, '/')) {
            return $path;
        }
        return base_path($path);
    }
}
