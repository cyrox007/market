<?php

namespace App\Console\Commands;

use App\Services\Catalog\Integrations\OpenCart\OpenCartXlsxReader;
use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class InspectOpenCartXlsxCommand extends Command
{
    protected $signature = 'catalog:inspect-opencart-xlsx {file : Path to xlsx (relative to backend) or absolute} {--rows=2 : How many data rows to show per sheet}';

    protected $description = 'Показать заголовки и несколько строк с каждого листа OpenCart XLSX';

    public function handle(): int
    {
        $file = (string) $this->argument('file');
        $rowsToShow = (int) $this->option('rows');
        $rowsToShow = max(1, min(20, $rowsToShow));

        $path = $this->resolvePath($file);
        if (! is_file($path)) {
            $this->error("Файл не найден: {$path}");
            return self::FAILURE;
        }

        $this->info("Файл: {$path}");

        $spreadsheet = IOFactory::load($path);
        $sheetNames = $spreadsheet->getSheetNames();

        $this->line('');
        $this->line('Листы:');
        foreach ($sheetNames as $i => $name) {
            $this->line(sprintf('  [%d] %s', $i, $name));
        }

        $reader = new OpenCartXlsxReader($path);

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $this->line('');
            $this->line(str_repeat('=', 90));
            $this->line('Sheet: ' . $sheet->getTitle());

            [$rawHeaders, $rawSample] = $this->readRawHeadersAndSample($sheet, $rowsToShow);

            $this->line('');
            $this->line('RAW headers:');
            $this->line($this->stringifyArray($rawHeaders));

            $this->line('');
            $this->line("RAW sample rows (first {$rowsToShow}):");
            foreach ($rawSample as $idx => $row) {
                $this->line(sprintf('  #%d %s', $idx + 1, $this->stringifyArray($row)));
            }

            $normalizedSample = $reader->readSheet($sheet->getTitle());
            $normalizedSample = array_slice($normalizedSample, 0, $rowsToShow);

            $this->line('');
            $this->line("Normalized sample rows (first {$rowsToShow}, keys=нормализованные заголовки):");
            foreach ($normalizedSample as $idx => $row) {
                $this->line(sprintf('  #%d %s', $idx + 1, json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)));
            }
        }

        $this->line('');
        $this->info('Готово.');

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
    private function readRawHeadersAndSample(Worksheet $sheet, int $rowsToShow): array
    {
        $highestRow = (int) $sheet->getHighestDataRow();
        $highestCol = (string) $sheet->getHighestDataColumn();
        $highestColIndex = Coordinate::columnIndexFromString($highestCol);

        // headers are in row 1
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
            $sample[] = $line;
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

