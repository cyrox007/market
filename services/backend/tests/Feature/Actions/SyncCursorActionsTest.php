<?php

declare(strict_types=1);

namespace Tests\Feature\Actions;

use App\Actions\Inventory\Sync\Get1CSyncCursorAction;
use App\Actions\Inventory\Sync\Store1CSyncCursorAction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SyncCursorActionsTest extends TestCase
{
    public function test_get_cursor_uses_lookback_by_default_when_cache_is_empty(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-04-06T10:00:00.000Z'));

        config()->set('catalog_import.config.svetofor_1c.minute_sync_cursor_key', 'test:cursor:key');
        config()->set('catalog_import.config.svetofor_1c.initial_updated_after', '2000-01-01T00:00:00.000Z');
        config()->set('catalog_import.config.svetofor_1c.minute_sync_default_lookback_minutes', 5);
        config()->set('catalog_import.config.svetofor_1c.minute_sync_use_initial_cursor', false);
        Cache::forget('test:cursor:key');

        $cursor = (new Get1CSyncCursorAction())->execute();

        $this->assertSame('2026-04-06T09:55:00.000Z', $cursor);
    }

    public function test_store_cursor_overwrites_invalid_cached_value_without_throwing(): void
    {
        config()->set('catalog_import.config.svetofor_1c.minute_sync_cursor_key', 'test:cursor:key:invalid');
        Cache::forever('test:cursor:key:invalid', 'not-a-date');

        $action = new Store1CSyncCursorAction();
        $stored = $action->execute('2026-04-06T10:05:00.000Z');

        $this->assertSame('2026-04-06T10:05:00.000Z', $stored);
        $this->assertSame('2026-04-06T10:05:00.000Z', Cache::get('test:cursor:key:invalid'));
    }
}
