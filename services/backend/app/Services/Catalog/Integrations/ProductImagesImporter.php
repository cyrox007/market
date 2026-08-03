<?php

namespace App\Services\Catalog\Integrations;

use App\Jobs\ProcessProductImages;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ProductImagesImporter
{
    public function import(string $filePath): void
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            throw new \Exception("File \"{$filePath}\" does not exist or is not readable.");
        }

        try {
            $spreadsheet = IOFactory::load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();

            // Пропускаем заголовок
            $header = array_shift($rows);

            // Индексы колонок (0-based) – сверьте с вашим файлом!
            $colExternalId = 1;      // B
            $colMainPhoto = 15;      // P
            $colAdditional = 16;     // Q

            foreach ($rows as $rowIndex => $row) {
                $externalId = trim($row[$colExternalId] ?? '');
                $mainPhoto = trim($row[$colMainPhoto] ?? '');
                $additionalRaw = trim($row[$colAdditional] ?? '');

                if (empty($externalId) || empty($mainPhoto)) {
                    Log::info("Пропущена строка {$rowIndex}: нет артикула или главного фото");
                    continue;
                }

                // Разбиваем дополнительные ссылки (разделители: пробел, перенос строки, точка с запятой)
                $additionalUrls = preg_split('/[\s;,]+/', $additionalRaw, -1, PREG_SPLIT_NO_EMPTY);

                ProcessProductImages::dispatch($externalId, $mainPhoto, $additionalUrls);
            }

            Log::info('Импорт изображений завершён, задачи поставлены в очередь');
        } catch (\Exception $e) {
            Log::error('Ошибка при импорте изображений: ' . $e->getMessage());
            throw $e;
        }
    }
}