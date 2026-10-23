<?php

namespace App\Services\Catalog\Transfer;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** File-local, explicit groups. Never infer membership from adjacent rows or names. */
class TransferVariantGroups
{
    public static function column(array $headers): ?string
    {
        foreach (['variant_group', 'variation', 'группа вариантов'] as $name) {
            if (isset($headers[$name])) {
                return $headers[$name];
            }
        }

        return null;
    }

    public function index(Worksheet $sheet, int $header, string $column, ?string $oldColumn, ?string $nameColumn, array $sharedColumns = []): array
    {
        $groups = [];
        for ($row = $header + 1; $row <= $sheet->getHighestDataRow(); $row++) {
            $cell = $sheet->getCell($column.$row);
            if ($cell->getDataType() === 'f') {
                continue;
            }
            $group = trim((string) $cell->getValue());
            if ($group === '') {
                continue;
            }
            $groups[$group] ??= ['anchors' => [], 'rows' => []];
            $groups[$group]['rows'][] = $row;
            $oldCell = $oldColumn ? $sheet->getCell($oldColumn.$row) : null;
            $nameCell = $nameColumn ? $sheet->getCell($nameColumn.$row) : null;
            $old = $oldCell && $oldCell->getDataType() !== 'f' ? trim((string) $oldCell->getValue()) : '';
            if ($old !== '' && ctype_digit($old) && (int) $old > 0) {
                $groups[$group]['anchors'][$old] = [
                    'old_id' => $old,
                    'name' => $nameCell && $nameCell->getDataType() !== 'f' ? trim((string) $nameCell->getValue()) : '',
                ];
                foreach ($sharedColumns as $field => $sharedColumn) {
                    $sharedCell = $sheet->getCell($sharedColumn.$row);
                    if ($sharedCell->getDataType() !== 'f' && $sharedCell->getValue() !== null) {
                        $value = trim((string) $sharedCell->getValue());
                        if ($value !== '') {
                            $groups[$group]['anchors'][$old]['shared'][$field] = $value;
                        }
                    }
                }
            }
        }

        return $groups;
    }
}
