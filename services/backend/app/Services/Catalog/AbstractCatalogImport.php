<?php

namespace App\Services\Catalog;

use App\Services\Catalog\Contracts\CatalogImportInterface;
use App\Services\Catalog\DTO\CatalogImportResult;
use Illuminate\Support\Facades\Log;

abstract class AbstractCatalogImport implements CatalogImportInterface
{
    public static function getConfigKey(): ?string
    {
        return null;
    }

    public function import(array $options = []): CatalogImportResult
    {
        $start = microtime(true);
        $errors = [];
        $createdCategories = 0;
        $updatedCategories = 0;
        $createdProducts = 0;
        $updatedProducts = 0;

        try {
            $rawCategories = $this->fetchCategoriesRaw();
        } catch (\Throwable $e) {
            Log::error('Catalog import: fetch categories failed', [
                'provider' => static::getLabel(),
                'error' => $e->getMessage(),
            ]);
            $errors[] = 'Категории: ' . $e->getMessage();
            $rawCategories = [];
        }

        try {
            $rawProducts = $this->fetchProductsRaw();
        } catch (\Throwable $e) {
            Log::error('Catalog import: fetch products failed', [
                'provider' => static::getLabel(),
                'error' => $e->getMessage(),
            ]);
            $errors[] = 'Товары: ' . $e->getMessage();
            $rawProducts = [];
        }

        $mappedCategories = $this->mapCategories($rawCategories);
        $mappedProducts = $this->mapProducts($rawProducts);

        if (empty($errors)) {
            try {
                [$createdCategories, $updatedCategories] = $this->persistCategories($mappedCategories);
            } catch (\Throwable $e) {
                Log::error('Catalog import: persist categories failed', [
                    'provider' => static::getLabel(),
                    'error' => $e->getMessage(),
                ]);
                $errors[] = 'Сохранение категорий: ' . $e->getMessage();
            }

            if (empty($errors)) {
                try {
                    [$createdProducts, $updatedProducts] = $this->persistProducts($mappedProducts);
                } catch (\Throwable $e) {
                    Log::error('Catalog import: persist products failed', [
                        'provider' => static::getLabel(),
                        'error' => $e->getMessage(),
                    ]);
                    $errors[] = 'Сохранение товаров: ' . $e->getMessage();
                }
            }
        }

        $durationSeconds = round(microtime(true) - $start, 2);

        return new CatalogImportResult(
            createdCategories: $createdCategories,
            updatedCategories: $updatedCategories,
            createdProducts: $createdProducts,
            updatedProducts: $updatedProducts,
            errors: $errors,
            durationSeconds: $durationSeconds,
        );
    }

    /**
     * Загрузить сырой ответ категорий из источника.
     *
     * @return array<int, array<string, mixed>>
     */
    abstract protected function fetchCategoriesRaw(): array;

    /**
     * Загрузить сырой ответ товаров из источника.
     *
     * @return array<int, array<string, mixed>>
     */
    abstract protected function fetchProductsRaw(): array;

    /**
     * Преобразовать сырые категории в формат для сохранения.
     *
     * @param  array<int, array<string, mixed>>  $raw
     * @return array<int, array<string, mixed>>
     */
    abstract protected function mapCategories(array $raw): array;

    /**
     * Преобразовать сырые товары в формат для сохранения.
     *
     * @param  array<int, array<string, mixed>>  $raw
     * @return array<int, array<string, mixed>>
     */
    abstract protected function mapProducts(array $raw): array;

    /**
     * Сохранить категории в БД. Возвращает [created, updated].
     *
     * @param  array<int, array<string, mixed>>  $mapped
     * @return array{0: int, 1: int}
     */
    abstract protected function persistCategories(array $mapped): array;

    /**
     * Сохранить товары в БД. Возвращает [created, updated].
     *
     * @param  array<int, array<string, mixed>>  $mapped
     * @return array{0: int, 1: int}
     */
    abstract protected function persistProducts(array $mapped): array;
}
