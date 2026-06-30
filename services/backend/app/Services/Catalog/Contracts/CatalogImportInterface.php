<?php

namespace App\Services\Catalog\Contracts;

use App\Services\Catalog\DTO\CatalogImportResult;

interface CatalogImportInterface
{
    /**
     * Выполнить импорт каталога (категории и товары) из внешнего источника.
     *
     * @param  array<string, mixed>  $options  Опции импорта (например, dry_run, limit).
     * @return CatalogImportResult Результат с количеством созданных/обновлённых записей и ошибками.
     */
    public function import(array $options = []): CatalogImportResult;

    /**
     * Человекочитаемое название провайдера импорта (для отображения в админке).
     */
    public static function getLabel(): string;

    /**
     * Ключ настроек в config('catalog_import.config')[key].
     * Если null — класс не использует отдельный блок настроек.
     */
    public static function getConfigKey(): ?string;
}
