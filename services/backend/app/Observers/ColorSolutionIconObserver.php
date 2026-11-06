<?php

namespace App\Observers;

use App\Models\Product\Color\ColorSolution;
use App\Models\Product\Product;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/** Пиктограмма решения хранится в медиа: её замена сбрасывает кэш товаров. */
class ColorSolutionIconObserver
{
    public function saved(Media $media): void
    {
        $this->flush($media);
    }

    public function deleted(Media $media): void
    {
        $this->flush($media);
    }

    private function flush(Media $media): void
    {
        if ($media->model_type === ColorSolution::class) {
            Product::flushAllProductCaches();
        }
    }
}
