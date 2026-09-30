<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Product\Category;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class OzonCategoryImportRun extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_RUNNING = 'running';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'category_id',
        'user_id',
        'source_filename',
        'stored_path',
        'status',
        'total_rows',
        'created_products',
        'updated_products',
        'created_parents',
        'created_variants',
        'updated_variants',
        'images_queued',
        'skipped_rows',
        'summary',
        'errors',
        'error_message',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'summary' => 'array',
        'errors' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function markRunning(): void
    {
        $this->forceFill([
            'status' => self::STATUS_RUNNING,
            'started_at' => now(),
            'error_message' => null,
        ])->save();
    }

    /** @param array<string, mixed> $result */
    public function markSuccess(array $result): void
    {
        $this->forceFill([
            'status' => self::STATUS_SUCCESS,
            'total_rows' => (int) ($result['total_rows'] ?? $this->total_rows ?? 0),
            'created_products' => (int) ($result['created_products'] ?? 0),
            'updated_products' => (int) ($result['updated_products'] ?? 0),
            'created_parents' => (int) ($result['created_parents'] ?? 0),
            'created_variants' => (int) ($result['created_variants'] ?? 0),
            'updated_variants' => (int) ($result['updated_variants'] ?? 0),
            'images_queued' => (int) ($result['images_queued'] ?? 0),
            'skipped_rows' => (int) ($result['skipped_rows'] ?? 0),
            'summary' => $result,
            'errors' => $result['errors'] ?? [],
            'finished_at' => now(),
        ])->save();
    }

    public function markFailed(\Throwable $e): void
    {
        $this->forceFill([
            'status' => self::STATUS_FAILED,
            'error_message' => $e->getMessage(),
            'finished_at' => now(),
        ])->save();
    }
}
