<?php

namespace App\Listeners;

use App\Models\Product\Product;
use Illuminate\Support\Facades\Log;
use Spatie\MediaLibrary\Conversions\Events\ConversionHasBeenCompletedEvent;
use Spatie\MediaLibrary\Conversions\Events\ConversionWillStartEvent;
use Throwable;

final class LogProductThumbnailTiming
{
    public function handle(ConversionWillStartEvent|ConversionHasBeenCompletedEvent $event): void
    {
        $until = config('operator_diagnostics.until');
        $media = $event->media;

        if (! config('operator_diagnostics.enabled') || ! is_string($until) || strtotime($until) <= time()
            || $media->model_type !== (new Product)->getMorphClass()
            || ! in_array($media->collection_name, ['images', 'gallery'], true)
            || $event->conversion->getName() !== 'thumb' || $media->created_at === null) {
            return;
        }

        try {
            Log::channel('operator_diagnostics')->info('product_thumbnail_timing', [
                'media_id' => $media->getKey(),
                'collection' => $media->collection_name,
                'stage' => $event instanceof ConversionWillStartEvent ? 'started' : 'completed',
                'ms_since_media_created' => (int) $media->created_at->diffInMilliseconds(now(), absolute: true),
            ]);
        } catch (Throwable) {
            // Diagnostics must not interrupt image conversion.
        }
    }
}
