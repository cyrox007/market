<?php

namespace Database\Seeders;

use App\Models\Shipping\ShippingLocation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class NewShippingLocationsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Структура данных:
     * [
     *   [
     *     'federal_subject' => 'Краснодарский край',  // Столбец 1
     *     'district' => 'Азовский район',              // Столбец 2
     *     'locality' => 'г. Азов',                     // Столбец 3
     *     'delivery_price' => 150                       // Столбец 4
     *   ],
     *   ...
     * ]
     */
    public function run(): void
    {
        // Очищаем все существующие локации
        $this->command->info('Очистка существующих локаций...');
        
        // Удаляем все записи с учетом каскадного удаления
        // Начинаем с корневых элементов (без parent_id)
        $rootLocations = ShippingLocation::whereNull('parent_id')->get();
        foreach ($rootLocations as $location) {
            $location->delete(); // Каскадное удаление через модель
        }
        
        // Удаляем оставшиеся записи (на случай если что-то осталось)
        ShippingLocation::query()->delete();
        
        $this->command->info('Локации очищены.');

        // Загружаем данные из массива
        $data = $this->getLocationsData();

        $this->command->info('Начало заполнения локаций...');
        $this->command->info('Всего записей: ' . count($data));

        // Создаем структуру: Федеральный округ -> Федеральный субъект -> Район/Город -> Населенный пункт
        $federalDistricts = [];
        $federalSubjects = [];
        $districts = [];
        $localities = [];

        $processedCount = 0;
        foreach ($data as $index => $row) {
            $processedCount++;
            
            if ($processedCount % 100 === 0) {
                $this->command->info("Обработано записей: {$processedCount} / " . count($data));
            }
            
            $federalDistrictName = trim($row['federal_district'] ?? '');
            $federalSubjectName = trim($row['federal_subject']);
            $districtName = trim($row['district'] ?? '');
            $localityName = trim($row['locality']);
            $deliveryPrice = (float) $row['delivery_price'];

            if (empty($federalSubjectName) || empty($localityName)) {
                $this->command->warn("Пропущена строка " . ($index + 1) . ": отсутствуют обязательные поля");
                continue;
            }

            // Создаем или получаем федеральный округ (верхний уровень)
            $parentForSubject = null;
            if (!empty($federalDistrictName)) {
                if (!isset($federalDistricts[$federalDistrictName])) {
                    $federalDistrict = ShippingLocation::create([
                        'name' => $federalDistrictName,
                        'slug' => $this->generateSlug($federalDistrictName),
                        'type' => 'federal_district',
                        'location_type' => 'federal_district',
                        'is_active' => true,
                        'sort_order' => count($federalDistricts) + 1,
                    ]);
                    $federalDistricts[$federalDistrictName] = $federalDistrict;
                    $parentForSubject = $federalDistrict;
                } else {
                    $parentForSubject = $federalDistricts[$federalDistrictName];
                }
            }

            // Определяем тип федерального субъекта
            $federalSubjectType = $this->detectLocationType($federalSubjectName);
            
            // Создаем или получаем федеральный субъект
            // Ключ: округ|субъект (если есть округ) или просто субъект
            $subjectKey = $federalDistrictName ? $federalDistrictName . '|' . $federalSubjectName : $federalSubjectName;
            
            if (!isset($federalSubjects[$subjectKey])) {
                // Подсчитываем количество субъектов в том же округе (или без округа)
                $subjectCount = 0;
                if ($parentForSubject) {
                    $subjectCount = ShippingLocation::where('parent_id', $parentForSubject->id)
                        ->where('type', 'region')
                        ->count();
                } else {
                    $subjectCount = ShippingLocation::whereNull('parent_id')
                        ->where('type', 'region')
                        ->count();
                }
                
                $federalSubject = ShippingLocation::create([
                    'name' => $federalSubjectName,
                    'slug' => $this->generateSlug($federalSubjectName, $parentForSubject?->id),
                    'type' => 'region',
                    'location_type' => $federalSubjectType,
                    'parent_id' => $parentForSubject?->id,
                    'is_active' => true,
                    'sort_order' => $subjectCount + 1,
                ]);
                $federalSubjects[$subjectKey] = $federalSubject;
            } else {
                $federalSubject = $federalSubjects[$subjectKey];
            }

            // Если есть район/город, создаем промежуточный уровень
            $parentForLocality = $federalSubject;
            if (!empty($districtName)) {
                $districtKey = $federalSubject->id . '|' . $districtName;
                
                if (!isset($districts[$districtKey])) {
                    $districtType = $this->detectLocationType($districtName);
                    
                    // Подсчитываем количество районов в том же субъекте
                    $districtCount = ShippingLocation::where('parent_id', $federalSubject->id)
                        ->where('type', 'region')
                        ->where('id', '!=', $federalSubject->id)
                        ->count();
                    
                    $district = ShippingLocation::create([
                        'name' => $districtName,
                        'slug' => $this->generateSlug($districtName, $federalSubject->id),
                        'type' => 'region', // Промежуточный уровень
                        'location_type' => $districtType === 'city' ? 'city' : 'district',
                        'parent_id' => $federalSubject->id,
                        'is_active' => true,
                        'sort_order' => $districtCount + 1,
                    ]);
                    $districts[$districtKey] = $district;
                    $parentForLocality = $district;
                } else {
                    $parentForLocality = $districts[$districtKey];
                }
            }

            // Создаем населенный пункт
            $localityType = $this->detectLocalityType($localityName);
            $localityNameClean = $this->cleanLocalityName($localityName);
            
            // Подсчитываем количество населенных пунктов в том же родителе
            $localityCount = ShippingLocation::where('parent_id', $parentForLocality->id)
                ->where('type', 'locality')
                ->count();
            
            ShippingLocation::create([
                'name' => $localityName,
                'slug' => $this->generateSlug($localityNameClean, $parentForLocality->id),
                'type' => 'locality',
                'location_type' => $localityType,
                'parent_id' => $parentForLocality->id,
                'delivery_price' => $deliveryPrice,
                'is_active' => true,
                'sort_order' => $localityCount + 1,
            ]);

            // Отслеживаем количество для статистики
            if (!isset($localities[$parentForLocality->id])) {
                $localities[$parentForLocality->id] = [];
            }
            $localities[$parentForLocality->id][] = $localityName;
        }

        $this->command->info('Локации успешно заполнены!');
        $this->command->info('Федеральных округов: ' . count($federalDistricts));
        $this->command->info('Федеральных субъектов: ' . count($federalSubjects));
        $this->command->info('Районов/Городов: ' . count($districts));
        $this->command->info('Населенных пунктов: ' . count($data));
    }

    /**
     * Определить тип локации по названию
     */
    private function detectLocationType(string $name): string
    {
        $name = mb_strtolower($name);
        
        if (str_contains($name, 'республика') || str_contains($name, 'респ.')) {
            return 'republic';
        }
        if (str_contains($name, 'край')) {
            return 'krai';
        }
        if (str_contains($name, 'область') || str_contains($name, 'обл.')) {
            return 'oblast';
        }
        if (str_contains($name, 'автономный округ') || str_contains($name, 'ао')) {
            return 'autonomous_okrug';
        }
        if (str_contains($name, 'город федерального значения') || str_contains($name, 'г. москва') || str_contains($name, 'г. санкт-петербург')) {
            return 'federal_city';
        }
        if (str_contains($name, 'г. ') || str_contains($name, 'город')) {
            return 'city';
        }
        if (str_contains($name, 'район')) {
            return 'district';
        }
        
        return 'oblast'; // По умолчанию
    }

    /**
     * Определить тип населенного пункта
     */
    private function detectLocalityType(string $name): string
    {
        $name = mb_strtolower($name);
        
        if (str_starts_with($name, 'г. ') || str_starts_with($name, 'г.')) {
            return 'city';
        }
        if (str_starts_with($name, 'пгт ') || str_starts_with($name, 'пгт.')) {
            return 'urban_settlement';
        }
        if (str_starts_with($name, 'пос. ') || str_starts_with($name, 'пос.')) {
            return 'town';
        }
        if (str_starts_with($name, 'ст. ') || str_starts_with($name, 'ст.')) {
            return 'town';
        }
        if (str_starts_with($name, 'с. ') || str_starts_with($name, 'с.')) {
            return 'village';
        }
        
        return 'city'; // По умолчанию
    }

    /**
     * Очистить название населенного пункта от префиксов
     */
    private function cleanLocalityName(string $name): string
    {
        $name = trim($name);
        $prefixes = ['г. ', 'г.', 'пгт ', 'пгт.', 'пос. ', 'пос.', 'ст. ', 'ст.', 'с. ', 'с.'];
        
        foreach ($prefixes as $prefix) {
            if (str_starts_with(mb_strtolower($name), mb_strtolower($prefix))) {
                $name = trim(mb_substr($name, mb_strlen($prefix)));
                break;
            }
        }
        
        return $name;
    }

    /**
     * Генерировать уникальный slug
     */
    private function generateSlug(string $name, ?int $parentId = null): string
    {
        $slug = Str::slug($name, '-', 'ru');
        
        // Проверяем уникальность в рамках родителя
        $baseSlug = $slug;
        $counter = 1;
        
        while (ShippingLocation::where('parent_id', $parentId)
            ->where('slug', $slug)
            ->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }
        
        return $slug;
    }

    /**
     * Получить данные локаций
     * 
     * Поддерживает загрузку из CSV файла или массива
     * CSV формат: федеральный_субъект,район,населенный_пункт,стоимость_доставки
     */
    private function getLocationsData(): array
    {
        // Пробуем загрузить из CSV файла
        $csvPath = database_path('seeders/data/shipping_locations.csv');
        
        if (file_exists($csvPath)) {
            $this->command->info('Загрузка данных из CSV файла: ' . $csvPath);
            return $this->loadFromCsv($csvPath);
        }
        
        // Если CSV нет, используем встроенные данные
        $this->command->warn('CSV файл не найден. Используются встроенные данные.');
        $this->command->warn('Для загрузки данных создайте файл: ' . $csvPath);
        $this->command->warn('Формат CSV: федеральный_субъект,район,населенный_пункт,стоимость_доставки');
        
        // ПРИМЕР ДАННЫХ - замените на реальные данные из вашей таблицы
        return [
            // Краснодарский край
            [
                'federal_subject' => 'Краснодарский край',
                'district' => 'Азовский район',
                'locality' => 'г. Азов',
                'delivery_price' => 150,
            ],
            [
                'federal_subject' => 'Краснодарский край',
                'district' => 'Азовский район',
                'locality' => 'с. Астраханка',
                'delivery_price' => 150,
            ],
            [
                'federal_subject' => 'Краснодарский край',
                'district' => '',
                'locality' => 'г. Краснодар',
                'delivery_price' => 150,
            ],
            // Добавьте остальные данные из вашей таблицы здесь
            // ...
        ];
    }

    /**
     * Загрузить данные из CSV файла
     * Поддерживает разделители: точка с запятой (;) и запятая (,)
     * Формат: федеральный_округ;федеральный_субъект;район/населенный_пункт;стоимость_доставки
     */
    private function loadFromCsv(string $filePath): array
    {
        $data = [];
        
        // Определяем кодировку файла
        $content = file_get_contents($filePath);
        $encoding = mb_detect_encoding($content, ['UTF-8', 'Windows-1251', 'ISO-8859-1'], true);
        
        if ($encoding !== 'UTF-8') {
            $content = mb_convert_encoding($content, 'UTF-8', $encoding);
            $tempFile = tempnam(sys_get_temp_dir(), 'csv_');
            file_put_contents($tempFile, $content);
            $filePath = $tempFile;
        }
        
        $handle = fopen($filePath, 'r');
        
        if ($handle === false) {
            throw new \Exception("Не удалось открыть CSV файл: {$filePath}");
        }
        
        // Определяем разделитель по первой строке
        $firstLine = fgets($handle);
        rewind($handle);
        
        $delimiter = ';'; // По умолчанию точка с запятой
        if (strpos($firstLine, ',') !== false && strpos($firstLine, ';') === false) {
            $delimiter = ',';
        }
        
        // Пропускаем заголовок (первую строку), если он есть
        $header = fgetcsv($handle, 0, $delimiter);
        
        if ($header === false) {
            fclose($handle);
            throw new \Exception("CSV файл пуст или имеет неверный формат");
        }
        
        // Проверяем, является ли первая строка заголовком
        $isHeader = false;
        if (count($header) > 0) {
            $firstCell = mb_strtolower(trim($header[0] ?? ''));
            if (str_contains($firstCell, 'федеральный') || str_contains($firstCell, 'округ') || 
                str_contains($firstCell, 'субъект') || str_contains($firstCell, 'район') ||
                str_contains($firstCell, 'населенный') || str_contains($firstCell, 'стоимость')) {
                $isHeader = true;
            }
        }
        
        // Если первая строка не заголовок, возвращаемся к началу
        if (!$isHeader) {
            rewind($handle);
        }
        
        $lineNumber = $isHeader ? 1 : 0;
        $skippedCount = 0;
        
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $lineNumber++;
            
            // Пропускаем пустые строки
            if (empty(array_filter($row, function($cell) {
                return !empty(trim($cell ?? ''));
            }))) {
                continue;
            }
            
            // Ожидаем формат: федеральный_округ;федеральный_субъект;район/населенный_пункт;стоимость_доставки
            if (count($row) < 3) {
                $skippedCount++;
                if ($lineNumber <= 5) { // Показываем только первые 5 ошибок
                    $this->command->warn("Пропущена строка {$lineNumber}: недостаточно данных (найдено " . count($row) . " столбцов)");
                }
                continue;
            }
            
            $federalDistrict = trim($row[0] ?? '');
            $federalSubject = trim($row[1] ?? '');
            $locality = trim($row[2] ?? ''); // Это может быть район или населенный пункт
            $deliveryPrice = isset($row[3]) && !empty(trim($row[3])) ? (float) str_replace(',', '.', trim($row[3])) : 0;
            
            if (empty($federalSubject) || empty($locality)) {
                $skippedCount++;
                if ($lineNumber <= 5) {
                    $this->command->warn("Пропущена строка {$lineNumber}: отсутствуют обязательные поля");
                }
                continue;
            }
            
            // В CSV третий столбец может быть как районом, так и населенным пунктом
            // Если это район - создаем его как locality с типом district
            // Если это населенный пункт - создаем как обычный locality
            // Не создаем промежуточный уровень для районов, так как в данных нет вложенных населенных пунктов
            
            $data[] = [
                'federal_district' => $federalDistrict,
                'federal_subject' => $federalSubject,
                'district' => '', // Не используем промежуточный уровень
                'locality' => $locality, // Третий столбец всегда идет как locality
                'delivery_price' => $deliveryPrice,
            ];
        }
        
        fclose($handle);
        
        if (isset($tempFile) && file_exists($tempFile)) {
            unlink($tempFile);
        }
        
        $this->command->info("Загружено записей из CSV: " . count($data));
        if ($skippedCount > 0) {
            $this->command->warn("Пропущено невалидных строк: {$skippedCount}");
        }
        
        return $data;
    }
}
