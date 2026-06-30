<?php

declare(strict_types=1);

namespace App\Actions\Inventory\Sync;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class Get1CSyncCursorAction
{
    public function execute(): string
    {
        $key = (string) config('catalog_import.config.svetofor_1c.minute_sync_cursor_key', 'svetofor_1c:minute_sync:cursor');
        $initial = (string) config('catalog_import.config.svetofor_1c.initial_updated_after', '2000-01-01T00:00:00.000Z');
        $lookbackMinutes = max(1, (int) config('catalog_import.config.svetofor_1c.minute_sync_default_lookback_minutes', 5));
        $useInitialCursor = (bool) config('catalog_import.config.svetofor_1c.minute_sync_use_initial_cursor', false);
        $fallback = CarbonImmutable::now('UTC')->subMinutes($lookbackMinutes)->format('Y-m-d\TH:i:s.v\Z');

        $cursor = Cache::get($key);
        if (is_string($cursor) && $cursor !== '') {
            Log::debug('1C minute sync: cursor loaded from cache', [
                'cursor_key' => $key,
                'cursor' => $cursor,
            ]);

            return $cursor;
        }

        $resolvedFallback = ($useInitialCursor && $initial !== '') ? $initial : $fallback;

        Log::warning('1C minute sync: cursor missing, fallback applied', [
            'cursor_key' => $key,
            'fallback_cursor' => $resolvedFallback,
            'fallback_source' => $useInitialCursor ? 'initial_updated_after' : 'lookback_minutes',
        ]);

        return $resolvedFallback;
    }
}
