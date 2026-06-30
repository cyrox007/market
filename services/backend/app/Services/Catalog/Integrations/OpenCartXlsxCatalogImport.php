<?php

namespace App\Services\Catalog\Integrations;

use App\Models\Product\Attribute;
use App\Models\Product\AttributeValue;
use App\Models\Product\Category;
use App\Models\Product\Product;
use App\Services\Catalog\AbstractCatalogImport;
use App\Services\Catalog\Integrations\OpenCart\OpenCartXlsxReader;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Vanilo\Category\Models\Taxonomy;

/**
 * Импорт каталога из одного XLSX-файла выгрузки OpenCart (products-*.xlsx).
 * Листы: Products (товары и категории), ProductAttributes (атрибуты по product_id).
 */
class OpenCartXlsxCatalogImport extends AbstractCatalogImport
{
    protected const SHEET_PRODUCTS = 'Products';

    protected const SHEET_PRODUCT_ATTRIBUTES = 'ProductAttributes';

    protected const SHEET_PRODUCT_OPTION_VALUES = 'ProductOptionValues';

    protected const SHEET_ADDITIONAL_IMAGES = 'AdditionalImages';

    /** Количество повторов при deadlock (1213) при сохранении товара */
    private const DEADLOCK_RETRIES = 3;

    protected string $filePath;

    /** @var array<int|string, list<array{attribute_id: int|string, value: string}>> */
    protected array $attributesByProductId = [];

    /** @var array<int, int> external_id (имя категории или slug) => our category id */
    protected array $categoryIdMap = [];

    protected int $taxonomyId;

    /** @var array<string, mixed> */
    protected array $options = [];

    /** @var array<int|string, string> option_id/option_value_id => name (для будущего использования) */
    protected array $optionNames = [];

    protected array $optionValueNames = [];

    /** option_value_id => normalized image URL (из листа OptionValues при загрузке файла опций) */
    protected array $optionValueImageUrls = [];

    /**
     * По product_id (OpenCart) — список вариантов: каждая запись = option_id, option_value_id, quantity, price, price_prefix, image_url?.
     *
     * @var array<int|string, list<array{option_id: int|string, option_value_id: int|string, quantity: int, price: float, price_prefix: string}>>
     */
    protected array $productOptionValuesByProductId = [];

    /** external_id (OpenCart product_id) => our Product id (для разрешения related_ids) */
    protected array $productIdByExternalId = [];

    /** product_id (OpenCart) => list of image URLs (лист AdditionalImages) */
    protected array $additionalImagesByProductId = [];

    public function import(array $options = []): \App\Services\Catalog\DTO\CatalogImportResult
    {
        $this->options = $options;
        $productsPath = $options['file'] ?? $options['products_path'] ?? $options['path'] ?? '';
        if ($productsPath === '' && static::getConfigKey() !== null) {
            $productsPath = (string) (config('catalog_import.config.' . static::getConfigKey() . '.file', ''));
        }
        $productsPath = is_string($productsPath) ? trim($productsPath) : '';
        if ($productsPath === '') {
            return new \App\Services\Catalog\DTO\CatalogImportResult(
                errors: ['Укажите путь к XLSX товаров: file или products_path в опциях'],
                durationSeconds: 0.0,
            );
        }
        if (! is_file($productsPath) || ! is_readable($productsPath)) {
            return new \App\Services\Catalog\DTO\CatalogImportResult(
                errors: ['Файл товаров не найден или недоступен: ' . $productsPath],
                durationSeconds: 0.0,
            );
        }
        $this->filePath = $productsPath;
        $this->taxonomyId = $this->getTaxonomyId();

        $categoriesFile = $options['categories_file'] ?? $options['categories_path'] ?? null;
        $attributesFile = $options['attributes_file'] ?? $options['attributes_path'] ?? null;
        $optionsFile = $options['options_file'] ?? $options['options_path'] ?? null;

        $start = microtime(true);
        $errors = [];
        $createdCategories = 0;
        $updatedCategories = 0;
        $createdProducts = 0;
        $updatedProducts = 0;

        if ($categoriesFile !== null && $categoriesFile !== '' && is_file($categoriesFile)) {
            try {
                [$createdCategories, $updatedCategories] = $this->importCategoriesFromFile((string) $categoriesFile);
            } catch (\Throwable $e) {
                $errors[] = 'Категории из файла: ' . $e->getMessage();
                \Illuminate\Support\Facades\Log::error('OpenCart import categories from file', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            }
        } else {
            try {
                $rawCategories = $this->fetchCategoriesRaw();
                $mappedCategories = $this->mapCategories($rawCategories);
                [$createdCategories, $updatedCategories] = $this->persistCategories($mappedCategories);
            } catch (\Throwable $e) {
                $errors[] = 'Категории: ' . $e->getMessage();
            }
        }

        if ($attributesFile !== null && $attributesFile !== '' && is_file($attributesFile) && empty($errors)) {
            try {
                $this->importAttributesFromFile((string) $attributesFile);
            } catch (\Throwable $e) {
                $errors[] = 'Справочник атрибутов: ' . $e->getMessage();
            }
        }

        if ($optionsFile !== null && $optionsFile !== '' && is_file($optionsFile) && empty($errors)) {
            try {
                $this->loadOptionsFromFile((string) $optionsFile);
            } catch (\Throwable $e) {
                $errors[] = 'Справочник опций: ' . $e->getMessage();
            }
        }

        if (empty($errors)) {
            try {
                $rawProducts = $this->fetchProductsRaw();
                $mappedProducts = $this->mapProducts($rawProducts);
                [$createdProducts, $updatedProducts] = $this->persistProducts($mappedProducts);
            } catch (\Throwable $e) {
                $errors[] = 'Товары: ' . $e->getMessage();
                \Illuminate\Support\Facades\Log::error('OpenCart import products', ['error' => $e->getMessage()]);
            }
        }

        $durationSeconds = round(microtime(true) - $start, 2);

        return new \App\Services\Catalog\DTO\CatalogImportResult(
            createdCategories: $createdCategories,
            updatedCategories: $updatedCategories,
            createdProducts: $createdProducts,
            updatedProducts: $updatedProducts,
            errors: $errors,
            durationSeconds: $durationSeconds,
        );
    }

    /**
     * Импорт категорий из отдельного XLSX (лист Categories: category_id, parent_id, name, description, sort_order).
     *
     * @return array{0: int, 1: int} [created, updated]
     */
    protected function importCategoriesFromFile(string $path): array
    {
        $reader = new OpenCartXlsxReader($path);
        $rows = $reader->sheetExists('Categories') ? $reader->readSheet('Categories') : $reader->readFirstSheet();
        if (empty($rows)) {
            return [0, 0];
        }

        $created = 0;
        $updated = 0;
        $this->categoryIdMap = [];

        usort($rows, static function ($a, $b) {
            $pA = (int) ($a['parent_id'] ?? 0);
            $pB = (int) ($b['parent_id'] ?? 0);
            if ($pA !== $pB) {
                return $pA - $pB;
            }
            return ((int) ($a['sort_order'] ?? 0)) - ((int) ($b['sort_order'] ?? 0));
        });

        foreach ($rows as $row) {
            $ocId = $row['category_id'] ?? null;
            if ($ocId === null || $ocId === '') {
                continue;
            }
            $ocId = (string) $ocId;
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                $name = 'Категория ' . $ocId;
            }
            $slug = 'oc-cat-' . $ocId;
            $parentOcId = (int) ($row['parent_id'] ?? 0);
            $parentId = $parentOcId === 0 ? null : ($this->categoryIdMap[(string) $parentOcId] ?? null);
            $description = isset($row['description']) ? trim((string) $row['description']) : null;
            $priority = (int) ($row['sort_order'] ?? 0);

            $category = Category::updateOrCreate(
                ['slug' => $slug, 'taxonomy_id' => $this->taxonomyId],
                [
                    'name' => $name,
                    'parent_id' => $parentId,
                    'description' => $description,
                    'priority' => $priority,
                    'is_active' => true,
                ]
            );
            if ($category->wasRecentlyCreated) {
                $created++;
            } else {
                $updated++;
            }
            $this->categoryIdMap[$ocId] = $category->id;
        }

        return [$created, $updated];
    }

    /**
     * Загрузить справочник атрибутов из XLSX (лист Attributes: attribute_id, name) и обновить имена в БД.
     */
    protected function importAttributesFromFile(string $path): void
    {
        $reader = new OpenCartXlsxReader($path);
        $rows = $reader->sheetExists('Attributes') ? $reader->readSheet('Attributes') : [];
        foreach ($rows as $row) {
            $attrId = $row['attribute_id'] ?? null;
            if ($attrId === null || $attrId === '') {
                continue;
            }
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                $name = 'Атрибут ' . $attrId;
            }
            Attribute::updateOrCreate(
                ['slug' => 'opencart-' . $attrId],
                [
                    'name' => $name,
                    'type' => 'text',
                    'is_filterable' => false,
                    'is_use_in_variations' => false,
                    'allow_custom_value' => true,
                    'sort_order' => (int) ($row['sort_order'] ?? 0),
                ]
            );
        }
    }

    /**
     * Загрузить справочник опций в память (Options, OptionValues) для будущего использования.
     */
    protected function loadOptionsFromFile(string $path): void
    {
        $reader = new OpenCartXlsxReader($path);
        $this->optionNames = [];
        $this->optionValueNames = [];
        $optRows = $reader->sheetExists('Options') ? $reader->readSheet('Options') : [];
        foreach ($optRows as $row) {
            $id = $row['option_id'] ?? null;
            if ($id !== null && $id !== '') {
                $this->optionNames[(string) $id] = trim((string) ($row['name'] ?? 'Option ' . $id));
            }
        }
        $this->optionValueImageUrls = [];
        $valRows = $reader->sheetExists('OptionValues') ? $reader->readSheet('OptionValues') : [];
        foreach ($valRows as $row) {
            $id = $row['option_value_id'] ?? null;
            if ($id !== null && $id !== '') {
                $this->optionValueNames[(string) $id] = trim((string) ($row['name'] ?? 'Value ' . $id));
                $imgUrl = $this->normalizeImageUrl($row['image'] ?? $row['image_name'] ?? null);
                if ($imgUrl !== null) {
                    $this->optionValueImageUrls[(string) $id] = $imgUrl;
                }
            }
        }
    }

    public static function getLabel(): string
    {
        return 'OpenCart XLSX';
    }

    public static function getConfigKey(): ?string
    {
        return 'opencart_xlsx';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function fetchCategoriesRaw(): array
    {
        $reader = new OpenCartXlsxReader($this->filePath);
        $rows = $reader->sheetExists(self::SHEET_PRODUCTS)
            ? $reader->readSheet(self::SHEET_PRODUCTS)
            : $reader->readFirstSheet();
        $categories = [];
        $seen = [];
        foreach ($rows as $row) {
            foreach ($this->extractCategoryExternalIds($row) as $catId) {
                if ($catId === '' || isset($seen[$catId])) {
                    continue;
                }
                $seen[$catId] = true;
                $categories[] = [
                    'name' => 'Категория ' . $catId,
                    'external_id' => $catId,
                ];
            }
        }

        return $categories;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function fetchProductsRaw(): array
    {
        $reader = new OpenCartXlsxReader($this->filePath);
        $products = $reader->sheetExists(self::SHEET_PRODUCTS)
            ? $reader->readSheet(self::SHEET_PRODUCTS)
            : $reader->readFirstSheet();
        if ($reader->sheetExists(self::SHEET_PRODUCT_ATTRIBUTES)) {
            $this->attributesByProductId = $reader->readAttributesByProductId(self::SHEET_PRODUCT_ATTRIBUTES);
        } else {
            $this->attributesByProductId = [];
        }

        $this->productOptionValuesByProductId = [];
        if ($reader->sheetExists(self::SHEET_PRODUCT_OPTION_VALUES)) {
            $optValRows = $reader->readSheet(self::SHEET_PRODUCT_OPTION_VALUES);
            foreach ($optValRows as $r) {
                $pid = $r['product_id'] ?? null;
                if ($pid === null || $pid === '') {
                    continue;
                }
                $oid = $r['option_id'] ?? null;
                $ovid = $r['option_value_id'] ?? null;
                if ($oid === null || $ovid === null) {
                    continue;
                }
                $variantImageUrl = $this->normalizeImageUrl($r['image'] ?? $r['image_name'] ?? null);
                $this->productOptionValuesByProductId[$pid][] = [
                    'option_id' => $oid,
                    'option_value_id' => $ovid,
                    'quantity' => (int) ($r['quantity'] ?? 0),
                    'price' => (float) ($r['price'] ?? 0),
                    'price_prefix' => (string) ($r['price_prefix'] ?? '+'),
                    'image_url' => $variantImageUrl,
                ];
            }
        }

        $this->additionalImagesByProductId = [];
        if ($reader->sheetExists(self::SHEET_ADDITIONAL_IMAGES)) {
            $addRows = $reader->readSheet(self::SHEET_ADDITIONAL_IMAGES);
            foreach ($addRows as $r) {
                $pid = $r['product_id'] ?? null;
                if ($pid === null || $pid === '') {
                    continue;
                }
                $url = $this->normalizeImageUrl($r['image'] ?? $r['image_name'] ?? null);
                if ($url === null) {
                    continue;
                }
                $key = is_numeric($pid) ? (int) $pid : (string) $pid;
                if (! isset($this->additionalImagesByProductId[$key])) {
                    $this->additionalImagesByProductId[$key] = [];
                }
                $this->additionalImagesByProductId[$key][] = $url;
            }
        }

        $limit = $this->options['limit'] ?? null;
        if (is_numeric($limit) && (int) $limit > 0) {
            $products = array_slice($products, 0, (int) $limit);
        }

        return $products;
    }

    /**
     * @param  array<int, array<string, mixed>>  $raw
     * @return array<int, array<string, mixed>>
     */
    protected function mapCategories(array $raw): array
    {
        $mapped = [];
        foreach ($raw as $row) {
            $name = $row['name'] ?? $row['title'] ?? '';
            if ($name === '') {
                continue;
            }
            $name = trim((string) $name);
            $externalId = isset($row['external_id']) && $row['external_id'] !== '' ? (string) $row['external_id'] : null;
            $slug = $externalId !== null ? ('oc-cat-' . $externalId) : Str::slug($name);
            if ($slug === '') {
                $slug = 'category-' . substr(md5($name), 0, 8);
            }
            $mapped[] = [
                'name' => $name,
                'slug' => $slug,
                'external_id' => $externalId ?? $name,
                'parent_external_id' => null,
                'priority' => 0,
            ];
        }

        return $mapped;
    }

    /**
     * @param  array<int, array<string, mixed>>  $raw
     * @return array<int, array<string, mixed>>
     */
    protected function mapProducts(array $raw): array
    {
        $mapped = [];
        foreach ($raw as $row) {
            $name = $row['name'] ?? $row['title'] ?? $row['название'] ?? '';
            if ($name === null) {
                $name = '';
            }
            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }
            $productId = $row['product_id'] ?? $row['id'] ?? null;
            $externalId = $productId !== null && $productId !== '' ? (string) $productId : null;
            $sku = $row['sku'] ?? null;
            if ($sku === null || $sku === '') {
                $sku = $externalId !== null ? ('oc-' . $externalId) : ($row['model'] ?? Str::slug($name));
            }
            $price = (float) ($row['price'] ?? 0);
            $originalPrice = isset($row['original_price']) && $row['original_price'] !== '' ? (float) $row['original_price'] : null;
            $imageUrl = $this->normalizeImageUrl($row['image'] ?? $row['фото'] ?? null);
            $categoryExternalIds = $this->extractCategoryExternalIds($row);
            $attributes = [];
            if ($productId !== null && $productId !== '') {
                $pid = is_numeric($productId) ? (int) $productId : (string) $productId;
                $attributes = $this->attributesByProductId[$pid] ?? [];
            }
            $variants = [];
            if ($productId !== null && $productId !== '') {
                $pid = is_numeric($productId) ? (int) $productId : (string) $productId;
                $variants = $this->productOptionValuesByProductId[$pid] ?? [];
            }
            $relatedIds = $this->parseRelatedIds($row['related_ids'] ?? null);
            $additionalUrls = [];
            if ($productId !== null && $productId !== '') {
                $pid = is_numeric($productId) ? (int) $productId : (string) $productId;
                $additionalUrls = $this->additionalImagesByProductId[$pid] ?? [];
            }
            $mapped[] = [
                'name' => $name,
                'sku' => (string) $sku,
                'slug' => $this->buildUniqueProductSlug($name, $externalId),
                'price' => $price,
                'original_price' => $originalPrice,
                'description' => isset($row['description']) ? trim((string) $row['description']) : null,
                'external_id' => $externalId,
                'category_external_ids' => $categoryExternalIds,
                'attributes' => $attributes,
                'image_url' => $imageUrl,
                'additional_image_urls' => $additionalUrls,
                'variants' => $variants,
                'related_ids' => $relatedIds,
                'model' => trim((string) ($row['model'] ?? '')),
                'manufacturer' => trim((string) ($row['manufacturer'] ?? '')),
                'meta_title' => trim((string) ($row['meta_title'] ?? '')),
                'meta_description' => trim((string) ($row['meta_description'] ?? '')),
                'meta_keywords' => trim((string) ($row['meta_keywords'] ?? '')),
            ];
        }

        return $mapped;
    }

    /**
     * Парсит related_ids из выгрузки (список product_id через запятую).
     *
     * @return list<string>
     */
    protected function parseRelatedIds(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }
        $ids = array_map('trim', explode(',', (string) $value));
        $ids = array_filter($ids, fn ($id) => $id !== '' && $id !== '0');

        return array_values($ids);
    }

    /**
     * @param  array<int, array<string, mixed>>  $mapped
     * @return array{0: int, 1: int}
     */
    protected function persistCategories(array $mapped): array
    {
        $this->categoryIdMap = [];
        $created = 0;
        $updated = 0;

        foreach ($mapped as $row) {
            $slug = $row['slug'];
            $category = Category::updateOrCreate(
                ['slug' => $slug, 'taxonomy_id' => $this->taxonomyId],
                [
                    'name' => $row['name'],
                    'slug' => $slug,
                    'taxonomy_id' => $this->taxonomyId,
                    'description' => $row['description'] ?? null,
                    'is_active' => true,
                    'priority' => $row['priority'] ?? 0,
                    'parent_id' => null,
                ]
            );
            if ($category->wasRecentlyCreated) {
                $created++;
            } else {
                $updated++;
            }
            if (! empty($row['external_id'])) {
                $this->categoryIdMap[$row['external_id']] = $category->id;
                $this->categoryIdMap[$slug] = $category->id;
            }
        }

        return [$created, $updated];
    }

    /**
     * @param  array<int, array<string, mixed>>  $mapped
     * @return array{0: int, 1: int}
     */
    protected function persistProducts(array $mapped): array
    {
        $created = 0;
        $updated = 0;
        $skipImages = (bool) ($this->options['no_images'] ?? false);
        $this->productIdByExternalId = [];

        foreach ($mapped as $row) {
            if (! empty($row['variants'])) {
                [$c, $u] = $this->persistParentAndVariants($row, $skipImages);
                $created += $c;
                $updated += $u;
                $extId = $row['external_id'] ?? null;
                if ($extId !== null && $extId !== '') {
                    $parent = $this->findOrResolveProduct($row);
                    if ($parent !== null) {
                        $this->productIdByExternalId[(string) $extId] = $parent->id;
                    }
                }
                continue;
            }

            $product = $this->findOrResolveProduct($row);
            if ($product === null) {
                $product = new Product;
                $product->sku = $row['sku'];
                $product->state = Product::ACTIVE;
                $product->stock = 0;
                $product->backorder = false;
                $product->units_sold = 0;
            }

            $this->applyProductDataFromImport($product, $row, $product->exists);

            $saved = $this->persistOneProductWithRetry($product, $row, $skipImages);
            if ($saved) {
                if ($product->wasRecentlyCreated) {
                    $created++;
                } else {
                    $updated++;
                }
                $extId = $row['external_id'] ?? null;
                if ($extId !== null && $extId !== '') {
                    $this->productIdByExternalId[(string) $extId] = $product->id;
                }
            }
        }

        $this->syncRelatedProducts($mapped);

        return [$created, $updated];
    }

    /**
     * Сохранить один товар (save + taxons + attributes + опционально image) с повтором при deadlock.
     *
     * @param  array<string, mixed>  $row
     */
    private function persistOneProductWithRetry(Product $product, array $row, bool $skipImages): bool
    {
        $lastException = null;
        for ($attempt = 1; $attempt <= self::DEADLOCK_RETRIES; $attempt++) {
            try {
                DB::transaction(function () use ($product, $row, $skipImages): void {
                    $product->save();

                    $taxonIds = [];
                    foreach ($row['category_external_ids'] ?? [] as $extId) {
                        if (isset($this->categoryIdMap[$extId])) {
                            $taxonIds[] = $this->categoryIdMap[$extId];
                        }
                    }
                    $taxonIds = array_unique($taxonIds);
                    if (! empty($taxonIds)) {
                        $product->taxons()->syncWithoutDetaching($taxonIds);
                    }

                    $this->syncProductAttributes($product, $row['attributes'] ?? []);
                    $this->syncProductModelAndManufacturer($product, $row);

                    if (! $skipImages) {
                        $this->attachProductImagesFromRow($product, $row);
                    }
                    $this->syncProductSeoFromMeta($product, $row);
                });

                return true;
            } catch (\Throwable $e) {
                $lastException = $e;
                if ($this->isDeadlockException($e) && $attempt < self::DEADLOCK_RETRIES) {
                    usleep(random_int(50, 300) * 1000);
                    continue;
                }
                throw $e;
            }
        }

        if ($lastException !== null) {
            throw $lastException;
        }

        return false;
    }

    private function isDeadlockException(\Throwable $e): bool
    {
        $code = $e->getCode();
        if (in_array($code, [1213, '1213', 40001, '40001'], true)) {
            return true;
        }
        $message = $e->getMessage();
        return str_contains($message, '1213') || str_contains($message, 'Deadlock')
            || str_contains($message, 'Serialization failure');
    }

    /**
     * Создаёт родительский товар (is_variable=true) и дочерние варианты по опциям.
     * У вариантов наследуются категории, атрибуты, описание, изображение; цена/остаток из варианта.
     *
     * @param  array<string, mixed>  $row
     * @return array{0: int, 1: int}
     */
    protected function persistParentAndVariants(array $row, bool $skipImages): array
    {
        $created = 0;
        $updated = 0;
        $externalId = $row['external_id'] ?? null;
        if ($externalId === null || $externalId === '') {
            return [0, 0];
        }

        $parent = $this->findOrResolveProduct($row);
        if ($parent === null) {
            $parent = new Product;
            $parent->sku = $row['sku'];
            $parent->state = Product::ACTIVE;
            $parent->stock = 0;
            $parent->backorder = false;
            $parent->units_sold = 0;
        }
        $this->applyProductDataFromImport($parent, $row, $parent->exists);
        $parent->is_variable = true;

        if ($this->persistOneProductWithRetry($parent, $row, $skipImages)) {
            if ($parent->wasRecentlyCreated) {
                $created++;
            } else {
                $updated++;
            }
        }

        $basePrice = (float) ($row['price'] ?? 0);
        $parentSlug = (string) ($row['slug'] ?? '');

        foreach ($row['variants'] ?? [] as $variant) {
            $optionValueId = $variant['option_value_id'] ?? '';
            $variantName = $this->optionValueNames[(string) $optionValueId] ?? ('Вариант ' . $optionValueId);
            $variantSku = 'oc-' . $externalId . '-' . $optionValueId;
            $vPrice = (float) ($variant['price'] ?? 0);
            $variantPrice = ($variant['price_prefix'] ?? '+') === '-'
                ? $basePrice - $vPrice
                : $basePrice + $vPrice;
            $quantity = (int) ($variant['quantity'] ?? 0);

            $variantRow = [
                'name' => trim((string) ($row['name'] ?? '') . ' ' . $variantName),
                'sku' => $variantSku,
                'slug' => $parentSlug . '-v-' . $optionValueId,
                'price' => $variantPrice,
                'original_price' => null,
                'description' => $row['description'] ?? null,
                'external_id' => null,
                'category_external_ids' => $row['category_external_ids'] ?? [],
                'attributes' => $row['attributes'] ?? [],
                'image_url' => $variant['image_url'] ?? $this->optionValueImageUrls[(string) $optionValueId] ?? $row['image_url'] ?? null,
                'additional_image_urls' => $row['additional_image_urls'] ?? [],
            ];

            $variantProduct = Product::where('sku', $variantSku)->first();
            if ($variantProduct === null) {
                $variantProduct = new Product;
                $variantProduct->sku = $variantSku;
                $variantProduct->parent_product_id = $parent->id;
                $variantProduct->state = Product::ACTIVE;
                $variantProduct->stock = 0;
                $variantProduct->backorder = false;
                $variantProduct->units_sold = 0;
            } else {
                $variantProduct->parent_product_id = $parent->id;
            }
            $this->applyProductDataFromImport($variantProduct, $variantRow, $variantProduct->exists);
            $variantProduct->stock = $quantity;

            if ($this->persistOneProductWithRetry($variantProduct, $variantRow, $skipImages)) {
                if ($variantProduct->wasRecentlyCreated) {
                    $created++;
                } else {
                    $updated++;
                }
                $this->syncVariantAttributeForOption($variantProduct, $variantName);
            }
        }

        return [$created, $updated];
    }

    /**
     * Записывает значение опции в характеристику «Вариант» вариации (product_variant_attributes.custom_value),
     * чтобы пользователь выбирал вариацию по этому значению.
     */
    protected function syncVariantAttributeForOption(Product $variantProduct, string $optionValueName): void
    {
        $attr = Attribute::where('slug', Attribute::SLUG_VARIANT)->first();
        if ($attr === null) {
            return;
        }
        $value = trim($optionValueName);
        if ($value === '') {
            return;
        }
        $now = now();
        DB::table('product_variant_attributes')->updateOrInsert(
            [
                'product_id' => $variantProduct->id,
                'attribute_id' => $attr->id,
            ],
            [
                'attribute_value_id' => null,
                'custom_value' => mb_substr($value, 0, 255),
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }

    /**
     * Привязывает сопутствующие товары по related_ids из выгрузки (external_id → наши id).
     *
     * @param  array<int, array<string, mixed>>  $mapped
     */
    protected function syncRelatedProducts(array $mapped): void
    {
        foreach ($mapped as $row) {
            $externalId = $row['external_id'] ?? null;
            $relatedIds = $row['related_ids'] ?? [];
            if ($externalId === null || $externalId === '' || empty($relatedIds)) {
                continue;
            }
            $ourId = $this->productIdByExternalId[(string) $externalId] ?? null;
            if ($ourId === null) {
                continue;
            }
            $product = Product::find($ourId);
            if ($product === null) {
                continue;
            }
            foreach ($relatedIds as $relatedExtId) {
                $relatedExtId = (string) $relatedExtId;
                $relatedOurId = $this->productIdByExternalId[$relatedExtId] ?? null;
                if ($relatedOurId === null) {
                    continue;
                }
                $related = Product::find($relatedOurId);
                if ($related !== null) {
                    $product->attachRelatedProduct($related);
                }
            }
        }
    }

    /**
     * Нормализовать URL изображения: если относительный путь — добавить базовый https://svetofor-mebel.ru/image/
     */
    private function normalizeImageUrl(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $url = trim((string) $value);
        if ($url === '') {
            return null;
        }
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }
        $base = 'https://svetofor-mebel.ru/image/';
        $url = ltrim($url, '/');
        if (! str_starts_with($url, 'catalog/')) {
            $url = 'catalog/' . $url;
        }
        return $base . $url;
    }

    /**
     * Главное изображение по URL в коллекцию images (один файл).
     */
    private function attachMainImageFromUrl(Product $product, string $url): void
    {
        try {
            $product->clearMediaCollection('images');
            $product->addMediaFromUrl($url)
                ->toMediaCollection('images');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('OpenCart import: failed to attach image', [
                'product_id' => $product->id,
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Привязывает изображения из выгрузки: image_url → главное (images); AdditionalImages — первое в главное,
     * если главного не было, остальные в галерею (gallery).
     *
     * @param  array<string, mixed>  $row
     */
    private function attachProductImagesFromRow(Product $product, array $row): void
    {
        $mainUrl = ! empty($row['image_url']) ? trim((string) $row['image_url']) : null;
        $additionalUrls = $row['additional_image_urls'] ?? [];
        if (is_array($additionalUrls)) {
            $additionalUrls = array_values(array_filter(array_map(function ($u) {
                $u = trim((string) $u);
                return $u !== '' ? $u : null;
            }, $additionalUrls)));
        } else {
            $additionalUrls = [];
        }

        if ($mainUrl !== null) {
            $this->attachMainImageFromUrl($product, $mainUrl);
        } elseif (count($additionalUrls) > 0) {
            $first = $additionalUrls[0];
            $this->attachMainImageFromUrl($product, $first);
            $additionalUrls = array_slice($additionalUrls, 1);
        }

        foreach ($additionalUrls as $url) {
            try {
                $product->addMediaFromUrl($url)->toMediaCollection('gallery');
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('OpenCart import: failed to attach gallery image', [
                    'product_id' => $product->id,
                    'url' => $url,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * В OpenCart выгрузке поле categories — это список ID категорий через запятую.
     *
     * @return list<string>
     */
    private function extractCategoryExternalIds(array $row): array
    {
        $v = $row['category'] ?? $row['categories'] ?? $row['категория'] ?? null;
        if ($v === null || $v === '') {
            return [];
        }
        $v = trim((string) $v);
        if ($v === '') {
            return [];
        }
        $parts = preg_split('/\s*,\s*/u', $v) ?: [];
        $parts = array_map(static fn ($x) => trim((string) $x), $parts);
        $parts = array_values(array_filter($parts, static fn ($x) => $x !== ''));

        return $parts;
    }

    private function buildUniqueProductSlug(string $name, ?string $externalId): string
    {
        $base = Str::slug($name);
        if ($base === '') {
            $base = 'product';
        }
        if ($externalId !== null && $externalId !== '') {
            return $base . '-' . $externalId;
        }
        return $base;
    }

    private function getTaxonomyId(): int
    {
        $taxonomy = Taxonomy::firstOrCreate(
            ['slug' => 'product-categories'],
            ['name' => 'Product Categories', 'slug' => 'product-categories']
        );

        return (int) $taxonomy->id;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function findOrResolveProduct(array $row): ?Product
    {
        $query = Product::query()->whereNull('parent_product_id');
        $externalId = $row['external_id'] ?? null;
        if ($externalId !== null && $externalId !== '') {
            $found = (clone $query)->where('external_id', (string) $externalId)->first();
            if ($found !== null) {
                return $found;
            }
        }
        $sku = $row['sku'] ?? null;
        if ($sku !== null && $sku !== '') {
            return $query->where('sku', (string) $sku)->first();
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function applyProductDataFromImport(Product $product, array $row, bool $isUpdate): void
    {
        $product->name = (string) ($row['name'] ?? $product->name ?? '');
        $product->slug = (string) ($row['slug'] ?? Str::slug($row['name'] ?? $product->name ?? ''));
        $product->price = (float) ($row['price'] ?? $product->price ?? 0);

        if (! $isUpdate) {
            $product->original_price = array_key_exists('original_price', $row) ? ($row['original_price'] !== null && $row['original_price'] !== '' ? (float) $row['original_price'] : null) : null;
            $product->description = array_key_exists('description', $row) ? $row['description'] : null;
            $product->excerpt = array_key_exists('excerpt', $row) ? $row['excerpt'] : null;
            if (isset($row['external_id']) && $row['external_id'] !== null && $row['external_id'] !== '') {
                $product->external_id = (string) $row['external_id'];
            }
            return;
        }

        if (array_key_exists('original_price', $row)) {
            $product->original_price = $row['original_price'] !== null && $row['original_price'] !== ''
                ? (float) $row['original_price']
                : null;
        }
        if (array_key_exists('description', $row)) {
            $product->description = $row['description'] !== null ? (string) $row['description'] : null;
        }
        if (array_key_exists('excerpt', $row)) {
            $product->excerpt = $row['excerpt'] !== null ? (string) $row['excerpt'] : null;
        }
        if (isset($row['external_id']) && $row['external_id'] !== null && $row['external_id'] !== '') {
            $product->external_id = (string) $row['external_id'];
        }
    }

    /**
     * Привязать к товару атрибуты из импорта (OpenCart attribute_id -> наш Attribute по slug opencart-{id}).
     *
     * @param  list<array{attribute_id: int|string, value: string}>  $attributes
     */
    protected function syncProductAttributes(Product $product, array $attributes): void
    {
        if (empty($attributes)) {
            return;
        }

        $toAttach = [];
        foreach ($attributes as $item) {
            $attrId = $item['attribute_id'];
            $value = (string) ($item['value'] ?? '');
            $value = trim($value);
            if ($value === '') {
                continue;
            }
            // В таблице product_attribute_values.value тип string(255)
            $valueForDb = mb_substr($value, 0, 255);
            $slug = 'opencart-' . $attrId;
            $attr = Attribute::firstOrCreate(
                ['slug' => $slug],
                [
                    'name' => 'Атрибут ' . $attrId,
                    'type' => 'text',
                    'is_filterable' => false,
                    'is_use_in_variations' => false,
                    'allow_custom_value' => true,
                    'sort_order' => 0,
                ]
            );
            // slug для значений атрибутов должен быть коротким и стабильным (в XLSX бывают очень длинные тексты)
            $hash = substr(md5($value), 0, 10);
            $base = Str::slug(mb_substr($value, 0, 80));
            if ($base === '') {
                $base = 'v';
            }
            $valueSlug = $base . '-' . $hash;
            $attrValue = AttributeValue::firstOrCreate(
                [
                    'attribute_id' => $attr->id,
                    'slug' => $valueSlug,
                ],
                ['value' => $valueForDb, 'sort_order' => 0]
            );
            $toAttach[] = ['attribute_id' => $attr->id, 'attribute_value_id' => $attrValue->id];
        }

        if ($toAttach !== []) {
            $syncData = [];
            foreach ($toAttach as $pair) {
                $syncData[$pair['attribute_id']] = ['attribute_value_id' => $pair['attribute_value_id']];
            }
            $product->attributes()->syncWithoutDetaching($syncData);
        }
    }

    /**
     * Привязывает к товару характеристики «Модель» и «Производитель» из столбцов model и manufacturer.
     *
     * @param  array<string, mixed>  $row
     */
    protected function syncProductModelAndManufacturer(Product $product, array $row): void
    {
        $modelVal = trim((string) ($row['model'] ?? ''));
        $manufacturerVal = trim((string) ($row['manufacturer'] ?? ''));

        foreach (
            [
                'model' => $modelVal,
                Attribute::SLUG_MANUFACTURER => $manufacturerVal,
            ] as $attrSlug => $value
        ) {
            if ($value === '') {
                continue;
            }
            $attr = Attribute::where('slug', $attrSlug)->first();
            if ($attr === null) {
                continue;
            }
            $valueForDb = mb_substr($value, 0, 255);
            $base = Str::slug(mb_substr($value, 0, 80));
            if ($base === '') {
                $base = 'v';
            }
            $valueSlug = $base . '-' . substr(md5($value), 0, 10);
            $attrValue = AttributeValue::firstOrCreate(
                [
                    'attribute_id' => $attr->id,
                    'slug' => $valueSlug,
                ],
                ['value' => $valueForDb, 'sort_order' => 0]
            );
            $product->attributes()->detach($attr->id);
            $product->attributes()->attach($attr->id, ['attribute_value_id' => $attrValue->id]);
        }
    }

    /**
     * Записывает meta_title(ru-ru) и meta_description(ru-ru) в SEO товара при наличии.
     *
     * @param  array<string, mixed>  $row
     */
    protected function syncProductSeoFromMeta(Product $product, array $row): void
    {
        $title = trim((string) ($row['meta_title'] ?? ''));
        $description = trim((string) ($row['meta_description'] ?? ''));
        if ($title === '' && $description === '') {
            return;
        }
        if (! method_exists($product, 'seo')) {
            return;
        }
        $payload = [];
        if ($title !== '') {
            $payload['title'] = mb_substr($title, 0, 255);
        }
        if ($description !== '') {
            $payload['description'] = $description;
        }
        if ($payload === []) {
            return;
        }
        $product->seo()->updateOrCreate([], $payload);
    }
}
