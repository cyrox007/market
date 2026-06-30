<?php

namespace App\Services\Catalog\Integrations\OpenCart;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as SpreadsheetDate;

/**
 * Чтение листов XLSX выгрузки OpenCart (products-*.xlsx).
 * Листы: Products, ProductAttributes, AdditionalImages, ProductOptions, ProductOptionValues.
 */
class OpenCartXlsxReader
{
    /** Нормализация заголовков: возможные варианты названий -> единый ключ */
    private const HEADER_ALIASES = [
        'product_id' => ['product_id', 'id', 'ID', '№ п/п', 'no'],
        'name' => ['name', 'name(ru-ru)', 'name (ru-ru)', 'название', 'title', 'модель', 'name(ru_ru)', 'name (ru_ru)'],
        'category' => ['category', 'категория', 'categories'],
        'model' => ['model', 'модель'],
        'sku' => ['sku', 'артикул', 'upc'],
        'price' => ['price', 'цена', 'prices'],
        'original_price' => ['original_price', 'старая цена', 'old price'],
        'description' => ['description', 'description(ru-ru)', 'description (ru-ru)', 'описание', 'description(ru_ru)', 'description (ru_ru)'],
        'quantity' => ['quantity', 'количество', 'stock'],
        'image' => ['image', 'image_name', 'изображение', 'images', 'фото', 'photo'],
        'status' => ['status', 'статус'],
        'manufacturer' => ['manufacturer', 'производитель'],
        'attribute_group_id' => ['attribute_group_id'],
        'attribute_id' => ['attribute_id'],
        'text(ru-ru)' => ['text(ru-ru)', 'text (ru-ru)', 'value', 'значение'],
        'category_id' => ['category_id'],
        'parent_id' => ['parent_id'],
        'sort_order' => ['sort_order'],
        'option_id' => ['option_id'],
        'option_value_id' => ['option_value_id'],
        'related_ids' => ['related_ids', 'related ids', 'related', 'сопутствующие'],
        'meta_title' => ['meta_title(ru-ru)', 'meta_title (ru-ru)', 'meta_title(ru_ru)', 'meta_title'],
        'meta_description' => ['meta_description(ru-ru)', 'meta_description (ru-ru)', 'meta_description(ru_ru)', 'meta_description'],
        'meta_keywords' => ['meta_keywords(ru-ru)', 'meta_keywords (ru-ru)', 'meta_keywords(ru_ru)', 'meta_keywords'],
    ];

    private string $path;

    /** @var \PhpOffice\PhpSpreadsheet\Spreadsheet|null */
    private $spreadsheet = null;

    public function __construct(string $path)
    {
        $this->path = $path;
    }

    /**
     * Прочитать лист: первая строка = ключи (нормализованные), остальные = данные.
     *
     * @return array<int, array<string, mixed>>
     */
    public function readSheet(string $sheetName): array
    {
        $sheet = $this->getSheetByName($sheetName);
        if ($sheet === null) {
            return [];
        }

        return $this->readSheetFromWorksheet($sheet);
    }

    /**
     * @param  \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet  $sheet
     * @return array<int, array<string, mixed>>
     */
    private function readSheetFromWorksheet($sheet): array
    {
        $rows = [];
        $headerMap = null;

        foreach ($sheet->getRowIterator() as $row) {
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);
            $values = [];
            $colIndex = 0;
            foreach ($cellIterator as $cell) {
                $val = $this->getCellValue($cell);
                $values[$colIndex] = $val;
                $colIndex++;
            }

            if ($headerMap === null) {
                $headerMap = [];
                foreach ($values as $colIndex => $rawKey) {
                    $key = $this->normalizeHeaderKey(is_scalar($rawKey) ? trim((string) $rawKey) : '');
                    if ($key !== '') {
                        $headerMap[$colIndex] = $key;
                    }
                }
                continue;
            }

            $assoc = [];
            foreach ($values as $colIndex => $val) {
                if (isset($headerMap[$colIndex])) {
                    $assoc[$headerMap[$colIndex]] = $val;
                }
            }
            $rows[] = $assoc;
        }

        return $rows;
    }

    /**
     * Прочитать лист ProductAttributes и сгруппировать по product_id.
     *
     * @return array<int|string, list<array{attribute_id: int|string, value: string}>>
     */
    public function readAttributesByProductId(string $sheetName = 'ProductAttributes'): array
    {
        $rows = $this->readSheet($sheetName);
        $valueColumn = null;
        foreach (['text(ru-ru)', 'value', 'значение'] as $candidate) {
            if (! empty($rows) && array_key_exists($candidate, $rows[0])) {
                $valueColumn = $candidate;
                break;
            }
        }
        if ($valueColumn === null && ! empty($rows)) {
            $valueColumn = 'text(ru-ru)';
        }

        $byProduct = [];
        foreach ($rows as $row) {
            $productId = $row['product_id'] ?? null;
            if ($productId === null || $productId === '') {
                continue;
            }
            $productId = is_numeric($productId) ? (int) $productId : (string) $productId;
            $attrId = $row['attribute_id'] ?? null;
            $value = $valueColumn !== null ? ($row[$valueColumn] ?? '') : '';
            if ($value === null) {
                $value = '';
            }
            $value = trim((string) $value);
            if ($attrId === null && $attrId !== 0) {
                continue;
            }
            if (! isset($byProduct[$productId])) {
                $byProduct[$productId] = [];
            }
            $byProduct[$productId][] = [
                'attribute_id' => is_numeric($attrId) ? (int) $attrId : (string) $attrId,
                'value' => $value,
            ];
        }

        return $byProduct;
    }

    public function sheetExists(string $sheetName): bool
    {
        return $this->getSheetByName($sheetName) !== null;
    }

    /** @return array<string> */
    public function getSheetNames(): array
    {
        return $this->loadSpreadsheet()->getSheetNames();
    }

    /**
     * Прочитать первый лист (индекс 0) — для файлов, где лист товаров не назван "Products".
     *
     * @return array<int, array<string, mixed>>
     */
    public function readFirstSheet(): array
    {
        $spreadsheet = $this->loadSpreadsheet();
        if ($spreadsheet->getSheetCount() === 0) {
            return [];
        }
        $sheet = $spreadsheet->getSheet(0);

        return $this->readSheetFromWorksheet($sheet);
    }

    /**
     * @return \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet|null
     */
    private function getSheetByName(string $sheetName)
    {
        $spreadsheet = $this->loadSpreadsheet();
        $name = $spreadsheet->getSheetByName($sheetName);

        return $name !== null ? $name : null;
    }

    private function loadSpreadsheet(): \PhpOffice\PhpSpreadsheet\Spreadsheet
    {
        if ($this->spreadsheet === null) {
            if (! is_file($this->path) || ! is_readable($this->path)) {
                throw new \RuntimeException('Файл не найден или недоступен: ' . $this->path);
            }
            $this->spreadsheet = IOFactory::load($this->path);
        }

        return $this->spreadsheet;
    }

    private function getCellValue(\PhpOffice\PhpSpreadsheet\Cell\Cell $cell): mixed
    {
        $value = $cell->getValue();
        if ($value === null) {
            return null;
        }
        if (is_numeric($value) && \PhpOffice\PhpSpreadsheet\Shared\Date::isDateTime($cell)) {
            try {
                $value = SpreadsheetDate::excelToDateTimeObject($value);
                return $value->format('Y-m-d H:i:s');
            } catch (\Throwable) {
                return $value;
            }
        }

        return $value;
    }

    private function normalizeHeaderKey(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }
        $lower = mb_strtolower($raw);
        foreach (self::HEADER_ALIASES as $canonical => $aliases) {
            foreach ($aliases as $alias) {
                if (mb_strtolower($alias) === $lower) {
                    return $canonical;
                }
            }
        }
        return \Illuminate\Support\Str::slug($raw, '_');
    }
}
