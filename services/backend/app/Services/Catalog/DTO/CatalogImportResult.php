<?php

namespace App\Services\Catalog\DTO;

final class CatalogImportResult
{
    public function __construct(
        public int $createdCategories = 0,
        public int $updatedCategories = 0,
        public int $createdProducts = 0,
        public int $updatedProducts = 0,
        /** @var array<int, string> */
        public array $errors = [],
        public float $durationSeconds = 0.0,
    ) {}

    public function isSuccess(): bool
    {
        return empty($this->errors);
    }

    public function totalCategories(): int
    {
        return $this->createdCategories + $this->updatedCategories;
    }

    public function totalProducts(): int
    {
        return $this->createdProducts + $this->updatedProducts;
    }
}
