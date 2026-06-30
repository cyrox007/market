<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CatalogImportRun extends Model
{
    protected $table = 'catalog_import_runs';

    protected $fillable = [
        'importer_class',
        'status',
        'created_categories',
        'updated_categories',
        'created_products',
        'updated_products',
        'duration_seconds',
        'error_message',
        'errors',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'errors' => 'array',
        'duration_seconds' => 'float',
    ];

    public const STATUS_RUNNING = 'running';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    public static function recordFailure(string $importerClass, string $errorMessage): self
    {
        $run = self::create([
            'importer_class' => $importerClass,
            'status' => self::STATUS_FAILED,
            'error_message' => $errorMessage,
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        return $run;
    }

    public static function recordSuccess(
        string $importerClass,
        int $createdCategories,
        int $updatedCategories,
        int $createdProducts,
        int $updatedProducts,
        float $durationSeconds,
        array $errors = []
    ): self {
        $run = self::create([
            'importer_class' => $importerClass,
            'status' => empty($errors) ? self::STATUS_SUCCESS : self::STATUS_FAILED,
            'created_categories' => $createdCategories,
            'updated_categories' => $updatedCategories,
            'created_products' => $createdProducts,
            'updated_products' => $updatedProducts,
            'duration_seconds' => $durationSeconds,
            'errors' => $errors,
            'started_at' => now()->subSeconds((int) $durationSeconds),
            'finished_at' => now(),
        ]);

        return $run;
    }

    /**
     * Последний запуск по классу импортёра.
     */
    public static function lastRunFor(string $importerClass): ?self
    {
        return self::query()
            ->where('importer_class', $importerClass)
            ->whereNotNull('finished_at')
            ->orderByDesc('finished_at')
            ->first();
    }
}
