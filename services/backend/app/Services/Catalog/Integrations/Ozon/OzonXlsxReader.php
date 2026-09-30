<?php

declare(strict_types=1);

namespace App\Services\Catalog\Integrations\Ozon;

use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use RuntimeException;

final class OzonXlsxReader
{
    public const FIELD_EXTERNAL_ID = 'артикул';
    public const FIELD_NAME = 'название товара';
    public const FIELD_PRICE = 'предельная цена без акций, руб.';
    public const FIELD_ORIGINAL_PRICE = 'зачёркнутая цена, руб.';
    public const FIELD_ORIGINAL_PRICE_ALT = 'зачеркнутая цена, руб.';
    public const FIELD_GTIN = 'штрихкод (серийный номер / ean)';
    public const FIELD_PACKAGE_WEIGHT = 'вес в упаковке, г';
    public const FIELD_PACKAGE_WIDTH = 'ширина упаковки, мм';
    public const FIELD_PACKAGE_HEIGHT = 'высота упаковки, мм';
    public const FIELD_PACKAGE_LENGTH = 'длина упаковки, мм';
    public const FIELD_MAIN_IMAGE = 'ссылка на главное фото';
    public const FIELD_GALLERY = 'ссылки на дополнительные фото';
    public const FIELD_GROUP = 'название модели (для объединения в одну карточку)';
    public const FIELD_TEMPLATE_MODEL_NAME = 'название модели для шаблона наименования';
    public const FIELD_COLOR = 'цвет товара';
    public const FIELD_COLOR_NAME = 'название цвета';
    public const FIELD_DESCRIPTION = 'аннотация';
    public const FIELD_TYPE = 'тип';
    public const FIELD_ROW_NUMBER = '№';

    /**
     * @return array{
     *     sheet: string,
     *     header_row: int,
     *     headers: array<string, string>,
     *     rows: list<array<string, mixed>>
     * }
     */
    public function read(string $filePath): array
    {
        if (! is_file($filePath) || ! is_readable($filePath)) {
            throw new RuntimeException('Файл Ozon не найден или недоступен для чтения.');
        }

        $reader = IOFactory::createReaderForFile($filePath);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($filePath);
        $worksheet = $spreadsheet->getSheetByName('Шаблон') ?? $spreadsheet->getActiveSheet();
        $headerRow = $this->detectHeaderRow($worksheet);
        $highestColumn = $worksheet->getHighestDataColumn();
        $highestRow = $worksheet->getHighestDataRow();
        $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);

        $headersByColumn = [];
        $headers = [];

        for ($column = 1; $column <= $highestColumnIndex; $column++) {
            $original = $this->stringValue($worksheet->getCell([$column, $headerRow]));
            $normalized = self::normalizeHeader($original);
            if ($normalized === '') {
                continue;
            }

            if (! array_key_exists($normalized, $headers)) {
                $headers[$normalized] = $original;
                $headersByColumn[$column] = $normalized;
            }
        }

        foreach ([self::FIELD_EXTERNAL_ID, self::FIELD_NAME] as $required) {
            if (! array_key_exists($required, $headers)) {
                throw new RuntimeException('В файле Ozon не найдена обязательная колонка «' . $required . '».');
            }
        }

        $hasRowNumberColumn = array_key_exists(self::FIELD_ROW_NUMBER, $headers);

        $rows = [];
        for ($rowNumber = $headerRow + 1; $rowNumber <= $highestRow; $rowNumber++) {
            $fields = [];
            foreach ($headersByColumn as $column => $normalizedHeader) {
                $cell = $worksheet->getCell([$column, $rowNumber]);
                $fields[$normalizedHeader] = in_array($normalizedHeader, [self::FIELD_EXTERNAL_ID, self::FIELD_GTIN], true)
                    ? $this->formattedString($cell)
                    : $this->scalarValue($cell);
            }

            $externalId = $this->trimmedString($fields[self::FIELD_EXTERNAL_ID] ?? null);
            $name = $this->trimmedString($fields[self::FIELD_NAME] ?? null);
            if ($externalId === '' || $name === '') {
                continue;
            }

            $number = $fields[self::FIELD_ROW_NUMBER] ?? null;
            // В шаблонах Ozon строки с подсказками идут сразу после заголовка:
            // у них заполнены «Артикул» и «Название товара», но колонка № пустая.
            // Если колонка № присутствует, импортируем только реальные нумерованные строки.
            if ($hasRowNumberColumn && ! is_numeric($number)) {
                continue;
            }

            $rows[] = [
                'row_number' => $rowNumber,
                'external_id' => $externalId,
                'name' => $name,
                'price' => $fields[self::FIELD_PRICE] ?? null,
                'original_price' => $fields[self::FIELD_ORIGINAL_PRICE]
                    ?? $fields[self::FIELD_ORIGINAL_PRICE_ALT]
                    ?? null,
                'gtin' => $this->trimmedString($fields[self::FIELD_GTIN] ?? null),
                'package_weight_g' => $fields[self::FIELD_PACKAGE_WEIGHT] ?? null,
                'package_width_mm' => $fields[self::FIELD_PACKAGE_WIDTH] ?? null,
                'package_height_mm' => $fields[self::FIELD_PACKAGE_HEIGHT] ?? null,
                'package_length_mm' => $fields[self::FIELD_PACKAGE_LENGTH] ?? null,
                'main_image_url' => $this->trimmedString($fields[self::FIELD_MAIN_IMAGE] ?? null),
                'gallery_urls' => $this->splitUrls($fields[self::FIELD_GALLERY] ?? null),
                'group_name' => $this->trimmedString($fields[self::FIELD_GROUP] ?? null),
                'template_model_name' => $this->trimmedString($fields[self::FIELD_TEMPLATE_MODEL_NAME] ?? null),
                'color' => $this->trimmedString($fields[self::FIELD_COLOR] ?? null),
                'color_name' => $this->trimmedString($fields[self::FIELD_COLOR_NAME] ?? null),
                'description' => $this->trimmedString($fields[self::FIELD_DESCRIPTION] ?? null),
                'type' => $this->trimmedString($fields[self::FIELD_TYPE] ?? null),
                'fields' => $fields,
            ];
        }

        return [
            'sheet' => $worksheet->getTitle(),
            'header_row' => $headerRow,
            'headers' => $headers,
            'rows' => $rows,
        ];
    }

    public static function normalizeHeader(mixed $value): string
    {
        $text = trim((string) $value);
        $text = str_replace("\u{00A0}", ' ', $text);
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\*+$/u', '', $text) ?? $text;

        return mb_strtolower(trim($text));
    }

    /** @return list<string> */
    public function splitUrls(mixed $value): array
    {
        $raw = $this->trimmedString($value);
        if ($raw === '') {
            return [];
        }

        $parts = preg_split('/[\s;,]+/u', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique(array_filter(array_map(
            static fn ($url) => trim((string) $url),
            $parts,
        ))));
    }

    private function detectHeaderRow($worksheet): int
    {
        $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($worksheet->getHighestDataColumn());
        $maxRow = min(12, max(1, $worksheet->getHighestDataRow()));

        for ($row = 1; $row <= $maxRow; $row++) {
            $headers = [];
            for ($column = 1; $column <= $highestColumnIndex; $column++) {
                $normalized = self::normalizeHeader($this->stringValue($worksheet->getCell([$column, $row])));
                if ($normalized !== '') {
                    $headers[$normalized] = true;
                }
            }

            if (isset($headers[self::FIELD_EXTERNAL_ID], $headers[self::FIELD_NAME])) {
                return $row;
            }
        }

        throw new RuntimeException('Не удалось определить строку заголовков шаблона Ozon.');
    }

    private function scalarValue(Cell $cell): mixed
    {
        $value = $cell->getValue();
        if ($value instanceof RichText) {
            return trim($value->getPlainText());
        }

        if (is_string($value)) {
            return trim($value);
        }

        return $value;
    }

    private function stringValue(Cell $cell): string
    {
        return $this->trimmedString($this->scalarValue($cell));
    }

    private function formattedString(Cell $cell): string
    {
        try {
            return $this->trimmedString($cell->getFormattedValue());
        } catch (\Throwable) {
            return $this->stringValue($cell);
        }
    }

    private function trimmedString(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_float($value) && floor($value) === $value) {
            return (string) (int) $value;
        }

        return trim((string) $value);
    }
}
