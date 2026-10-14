<?php

namespace App\Models\Traits;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

trait InvalidatesFulfillment
{
    public static function bootInvalidatesFulfillment(): void
    {
        $invalidate = fn () => Cache::forever('fulfillment_revision', (string) Str::uuid());
        static::saved($invalidate);
        static::deleted($invalidate);
    }
}
