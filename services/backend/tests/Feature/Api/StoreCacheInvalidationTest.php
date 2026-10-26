<?php

namespace Tests\Feature\Api;

use App\Models\Page\Store;
use Tests\TestCase;

class StoreCacheInvalidationTest extends TestCase
{
    public function test_database_cache_is_invalidated_when_revision_key_does_not_exist(): void
    {
        config(['cache.default' => 'database']);
        \Illuminate\Support\Facades\Cache::forget('store_api_revision');
        $store = Store::create(['name' => 'Store', 'slug' => 'database-shop', 'phone' => '123', 'address' => 'Address', 'city' => 'City', 'is_active' => true]);
        $this->getJson('/api/v1/stores?city=City')->assertOk()->assertJsonCount(1, 'data');
        $store->update(['is_active' => false]);
        $this->getJson('/api/v1/stores?city=City')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_city_filtered_lists_and_city_options_change_immediately(): void
    {
        $store = Store::create(['name' => 'Store', 'slug' => 'shop', 'phone' => '123', 'address' => 'Address', 'city' => 'Old', 'is_active' => true]);
        $this->getJson('/api/v1/stores?city=Old')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/stores/cities')->assertOk()->assertJsonPath('cities.0', 'Old');
        $store->update(['city' => 'New']);
        $this->getJson('/api/v1/stores?city=Old')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/stores?city=New')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/stores/cities')->assertOk()->assertExactJson(['cities' => ['New']]);
    }

    public function test_deactivation_invalidates_city_list_and_direct_url(): void
    {
        $store = Store::create(['name' => 'Store', 'slug' => 'shop', 'phone' => '123', 'address' => 'Address', 'city' => 'City', 'is_active' => true]);
        $this->getJson('/api/v1/stores?city=City')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/stores/shop')->assertOk();
        $store->update(['is_active' => false]);
        $this->getJson('/api/v1/stores?city=City')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/stores/shop')->assertNotFound();
    }
}
