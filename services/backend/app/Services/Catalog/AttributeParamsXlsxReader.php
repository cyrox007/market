<?php

namespace App\Services\Catalog;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Ридер product_params.xlsx для атрибутов.
 *
 * Формат:
 * - строка 1: пустая (в текущем файле);
 * - строка 2: A = группа ("Мебель"), B/C возможные метки, остальное комментарии;
 * - заголовочные блоки:
 *   - строки 3–4, 18–19, 29–30 и т.д.;
 *   - B = "Вид номенклатуры", C = "Подвид номенклатуры";
 *   - дальше по колонкам идут части заголовков атрибутов (две строки на один атрибут).
 * - строки под заголовком (до следующего блока заголовков) содержат значения атрибутов
 *   и комбинации (A,B,C) для группировки по категориям.
 */
class AttributeParamsXlsxReader
{
    private string $path;

    public function __construct(?string $path = null)
    {
        $this->path = $path ?? storage_path('app/product_params.xlsx');
    }

    /**
     * Прочитать файл и вернуть описания атрибутов.
     *
     * @return array<int, array{
     *     name: string,
     *     column: int,
     *     header_rows: array<int, int>,
     *     value_rows: array<int, int>,
     *     values: string[],
     *     categories: array<int, array{group: ?string, type: ?string, subtype: ?string}>
     * }>
     */
    public function read(): array
    {
        if (! is_file($this->path) || ! is_readable($this->path)) {
            throw new \RuntimeException('Файл не найден или недоступен: ' . $this->path);
        }

        $spreadsheet = IOFactory::load($this->path);
        $sheet = $spreadsheet->getSheet(0);

        $highestRow = (int) $sheet->getHighestDataRow();
        $highestCol = (string) $sheet->getHighestDataColumn();
        $highestColIndex = Coordinate::columnIndexFromString($highestCol);

        // Считаем заголовочными блоками пары строк, где B == "Вид номенклатуры", C == "Подвид номенклатуры".
        $headerBlocks = [];
        for ($row = 2; $row <= $highestRow; $row++) {
            $b = (string) $sheet->getCell('B' . $row)->getFormattedValue();
            $c = (string) $sheet->getCell('C' . $row)->getFormattedValue();

            if (trim($b) === 'Вид номенклатуры' && trim($c) === 'Подвид номенклатуры') {
                $headerBlocks[] = [$row, $row + 1];
            }
        }

        if ($headerBlocks === []) {
            return [];
        }

        // Добавляем "конечный" блок, чтобы проще считать диапазоны значений до конца листа
        $blocksWithEnd = [];
        foreach ($headerBlocks as $index => [$start, $end]) {
            $nextStart = $headerBlocks[$index + 1][0] ?? ($highestRow + 1);
            $blocksWithEnd[] = [
                'header_start' => $start,
                'header_end' => $end,
                'values_start' => $end + 1,
                'values_end' => $nextStart - 1,
            ];
        }

        $attributes = [];

        foreach ($blocksWithEnd as $block) {
            $headerStart = $block['header_start'];
            $headerEnd = $block['header_end'];
            $valuesStart = $block['values_start'];
            $valuesEnd = $block['values_end'];

            if ($valuesStart > $valuesEnd) {
                continue;
            }

            // Строка 2 (row = headerStart - 1) содержит группу (A) и возможно типы.
            $groupRowIndex = max(2, $headerStart - 1);

            for ($col = 4; $col <= $highestColIndex; $col++) {
                $cellAddrHeader1 = Coordinate::stringFromColumnIndex($col) . (string) $headerStart;
                $cellAddrHeader2 = Coordinate::stringFromColumnIndex($col) . (string) $headerEnd;
                $h1 = trim((string) $sheet->getCell($cellAddrHeader1)->getFormattedValue());
                $h2 = trim((string) $sheet->getCell($cellAddrHeader2)->getFormattedValue());

                if ($h1 === '' && $h2 === '') {
                    continue;
                }

                $nameParts = array_filter([$h1, $h2], static fn (string $v): bool => $v !== '');
                $attributeName = trim(implode(' ', $nameParts));
                if ($attributeName === '') {
                    continue;
                }

                $values = [];
                $categories = [];

                for ($row = $valuesStart; $row <= $valuesEnd; $row++) {
                    $cellAddrValue = Coordinate::stringFromColumnIndex($col) . (string) $row;
                    $rawValue = $sheet->getCell($cellAddrValue)->getFormattedValue();
                    $value = trim((string) $rawValue);

                    $group = trim((string) $sheet->getCell('A' . $row)->getFormattedValue());
                    $type = trim((string) $sheet->getCell('B' . $row)->getFormattedValue());
                    $subtype = trim((string) $sheet->getCell('C' . $row)->getFormattedValue());

                    if ($group !== '' || $type !== '' || $subtype !== '') {
                        $categories[] = [
                            'group' => $group !== '' ? $group : null,
                            'type' => $type !== '' ? $type : null,
                            'subtype' => $subtype !== '' ? $subtype : null,
                        ];
                    }

                    if ($value === '') {
                        continue;
                    }

                    $values[] = $value;
                }

                $values = array_values(array_unique($values));

                $attributes[] = [
                    'name' => $attributeName,
                    'column' => $col,
                    'header_rows' => [$headerStart, $headerEnd],
                    'value_rows' => [$valuesStart, $valuesEnd],
                    'values' => $values,
                    'categories' => $categories,
                ];
            }
        }

        return $attributes;
    }
}

