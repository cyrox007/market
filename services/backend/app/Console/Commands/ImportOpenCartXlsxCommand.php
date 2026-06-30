<?php

namespace App\Console\Commands;

use App\Services\Catalog\Integrations\OpenCartXlsxCatalogImport;
use Illuminate\Console\Command;

class ImportOpenCartXlsxCommand extends Command
{
    protected $signature = 'catalog:import-opencart-xlsx
                            {file : Путь к XLSX (например products-2026-03-03-start-52-end-598.xlsx)}
                            {--limit= : Ограничить кол-во строк Products для теста (например 10)}
                            {--no-images : Не скачивать и не прикреплять изображения}';

    protected $description = 'Импорт каталога из одного XLSX выгрузки OpenCart (листы Products, ProductAttributes)';

    public function handle(): int
    {
        $path = $this->argument('file');
        $path = is_string($path) ? trim($path) : '';
        if ($path === '') {
            $this->error('Укажите путь к файлу XLSX.');
            return self::FAILURE;
        }
        if (! is_file($path) || ! is_readable($path)) {
            $this->error('Файл не найден или недоступен: ' . $path);
            return self::FAILURE;
        }

        $this->info('Импорт из: ' . $path);
        $importer = app(OpenCartXlsxCatalogImport::class);
        $limit = $this->option('limit');
        $noImages = (bool) $this->option('no-images');

        $options = ['file' => $path];
        if ($limit !== null && $limit !== '') {
            $options['limit'] = (int) $limit;
        }
        if ($noImages) {
            $options['no_images'] = true;
        }

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
        return self::SUCCESS;
    }
}
