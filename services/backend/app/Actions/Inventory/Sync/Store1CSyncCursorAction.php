<?php

declare(strict_types=1);

namespace App\Actions\Inventory\Sync;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class Store1CSyncCursorAction
{
    public function execute(string $nextCursor): string
    {
        $key = (string) config('catalog_import.config.svetofor_1c.minute_sync_cursor_key', 'svetofor_1c:minute_sync:cursor');
        $current = Cache::get($key);
        $resolvedCursor = $nextCursor;

        try {
            $nextTs = CarbonImmutable::parse($nextCursor)->getTimestamp();

            if (is_string($current) && $current !== '') {
                $currentTs = null;
                try {
                    $currentTs = CarbonImmutable::parse($current)->getTimestamp();
                } catch (\Throwable) {
                    Log::warning('1C minute sync: invalid current cursor in cache, overwrite applied', [
                        'cursor_key' => $key,
                        'current_cursor' => $current,
                        'attempted_cursor' => $nextCursor,
                    ]);
                }

                if ($currentTs !== null && $nextTs < $currentTs) {
                    Log::warning('1C minute sync: cursor regression prevented', [
                        'cursor_key' => $key,
                        'current_cursor' => $current,
                        'attempted_cursor' => $nextCursor,
                    ]);
                    $resolvedCursor = $current;
                }
            }

            Cache::forever($key, $resolvedCursor);
            Log::info('1C minute sync: cursor stored', [
                'cursor_key' => $key,
                'cursor' => $resolvedCursor,
            ]);

            return $resolvedCursor;
        } catch (\Throwable $e) {
            Log::error('1C minute sync: failed to store cursor', [
                'cursor_key' => $key,
                'attempted_cursor' => $nextCursor,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
