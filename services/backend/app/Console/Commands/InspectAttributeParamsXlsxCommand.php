<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class InspectAttributeParamsXlsxCommand extends Command
{
    /**
     * Простой инспектор структуры файла product_params.xlsx.
     *
     * Пример:
     *  php artisan catalog:inspect-attribute-params
     *  php artisan catalog:inspect-attribute-params --rows=40
     *  php artisan catalog:inspect-attribute-params --path=services/backend/storage/app/product_params.xlsx
     */
    protected $signature = 'catalog:inspect-attribute-params
                            {--path= : Путь к XLSX (по умолчанию storage/app/product_params.xlsx)}
                            {--rows=40 : Сколько строк показать (не включая строку заголовков)}';

    protected $description = 'Показать заголовки и несколько строк из product_params.xlsx для анализа структуры атрибутов';

    public function handle(): int
    {
        $rowsToShow = (int) $this->option('rows');
        $rowsToShow = max(1, min(200, $rowsToShow));

        $pathOption = $this->option('path');
        if (is_string($pathOption) && $pathOption !== '') {
            $path = $this->resolvePath($pathOption);
        } else {
            $path = storage_path('app/product_params.xlsx');
        }

        if (! is_file($path)) {
            $this->error("Файл не найден: {$path}");

            return self::FAILURE;
        }

        if (! is_readable($path)) {
            $this->error("Файл недоступен для чтения: {$path}");

            return self::FAILURE;
        }

        $this->info("Файл: {$path}");

        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getSheet(0);

        $this->line('');
        $this->line('Лист: ' . $sheet->getTitle());

        [$headers, $sampleRows] = $this->readHeadersAndSample($sheet, $rowsToShow);

        $this->line('');
        $this->line('Заголовки (строка 1):');
        $this->line($this->stringifyArray($headers));

        $this->line('');
        $this->line("Первые {$rowsToShow} строк (начиная со 2-й):");
        foreach ($sampleRows as $rowIndex => $row) {
            $this->line(sprintf('  #%d %s', $rowIndex, $this->stringifyArray($row)));
        }

        $this->line('');
        $this->info('Готово. Используйте номера строк (3–4, 18–19, 29–30, 44–45 и т.д.), чтобы описать блоки атрибутов и их значений.');

        return self::SUCCESS;
    }

    private function resolvePath(string $file): string
    {
        if (str_starts_with($file, '/')) {
            return $file;
        }

        return base_path($file);
    }

    /**
     * @return array{0: array<int, mixed>, 1: array<int, array<int, mixed>>}
     */
    private function readHeadersAndSample(Worksheet $sheet, int $rowsToShow): array
    {
        $highestRow = (int) $sheet->getHighestDataRow();
        $highestCol = (string) $sheet->getHighestDataColumn();
        $highestColIndex = Coordinate::columnIndexFromString($highestCol);

        $headers = [];
        for ($col = 1; $col <= $highestColIndex; $col++) {
            $cellAddr = Coordinate::stringFromColumnIndex($col) . '1';
            $headers[] = $sheet->getCell($cellAddr)->getFormattedValue();
        }

        $sample = [];
        $dataStartRow = 2;
        $dataEndRow = min($highestRow, $dataStartRow + $rowsToShow - 1);
        for ($row = $dataStartRow; $row <= $dataEndRow; $row++) {
            $line = [];
            for ($col = 1; $col <= $highestColIndex; $col++) {
                $cellAddr = Coordinate::stringFromColumnIndex($col) . (string) $row;
                $line[] = $sheet->getCell($cellAddr)->getFormattedValue();
            }
            $sample[$row] = $line;
        }

        return [$headers, $sample];
    }

    /**
     * @param  array<int, mixed>  $arr
     */
    private function stringifyArray(array $arr): string
    {
        return json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}

