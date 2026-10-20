<?php

namespace App\Models\Product;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Инструкция или другой документ товара: название для витрины + один файл.
 */
class ProductDocument extends Model implements HasMedia
{
    use InteractsWithMedia;

    public const MEDIA_COLLECTION = 'file';

    public const MIME_TYPES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.oasis.opendocument.text',
        'application/vnd.oasis.opendocument.spreadsheet',
        'application/rtf',
        'text/plain',
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    protected $fillable = [
        'product_id',
        'title',
        'sort_order',
        'is_published',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_published' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function registerMediaCollections(): void
    {
        // Явный диск: загрузчик админки иначе берёт FILESYSTEM_DISK, а ссылка должна быть публичной.
        $this->addMediaCollection(self::MEDIA_COLLECTION)
            ->useDisk(config('media-library.disk_name', 'public'))
            ->acceptsMimeTypes(self::MIME_TYPES)
            ->singleFile();
    }

    public function file(): ?Media
    {
        return $this->getFirstMedia(self::MEDIA_COLLECTION);
    }
}
