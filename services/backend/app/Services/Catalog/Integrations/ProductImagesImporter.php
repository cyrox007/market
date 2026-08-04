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
            throw new \Exception("Файл \"{$filePath}\" не найден или недоступен для чтения.");
        }

        try {
            $spreadsheet = IOFactory::load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();
            
            // Получаем итератор строк (экономия памяти)
            $rowIterator = $worksheet->getRowIterator(2); 

            // Индексы колонок (1 - это A, 2 - это B и т.д.)
            $colExternalId = 2;      // Колонка B (Артикул)
            $colMainPhoto = 16;      // Колонка P (Главное фото)
            $colAdditional = 17;     // Колонка Q (Дополнительные фото)
            
            foreach ($rowIterator as $row) {
                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(false); // Считываем даже пустые ячейки

                $cells = [];
                foreach ($cellIterator as $cell) {
                    $cells[] = $cell->getValue();
                }

                $externalId = trim($cells[$colExternalId - 1] ?? '');
                $mainPhoto = trim($cells[$colMainPhoto - 1] ?? '');
                $additionalRaw = trim($cells[$colAdditional - 1] ?? '');

                // Пропускаем, если нет артикула или главного фото
                if (empty($externalId) || empty($mainPhoto)) {
                    continue;
                }

                // Разбиваем дополнительные ссылки (разделители: пробел, перенос строки, точка с запятой)
                $additionalUrls = preg_split('/[\s;,]+/', $additionalRaw, -1, PREG_SPLIT_NO_EMPTY);

                ProcessProductImages::dispatch($externalId, $mainPhoto, $additionalUrls);
                //Log::info("Артикул {$externalId} отправлен в обработку");
            }

            Log::info('Импорт изображений завершён, задачи поставлены в очередь');
        } catch (\Exception $e) {
            Log::error('Ошибка при импорте изображений: ' . $e->getMessage());
            throw $e;
        }
    }
}