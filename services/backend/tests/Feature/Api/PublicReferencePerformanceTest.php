<?php

namespace Tests\Feature\Api;

use App\Models\Product\Category;
use App\Models\Product\Room;
use Tests\TestCase;

class PublicReferencePerformanceTest extends TestCase
{
    public function test_menu_is_small_unicode_and_does_not_replace_the_full_contract(): void
    {
        $root = Category::factory()->create(['name' => 'Мягкая мебель', 'slug' => 'mebel', 'parent_id' => null, 'is_active' => true]);
        $child = Category::factory()->create(['name' => 'Диваны', 'slug' => 'divany', 'parent_id' => $root->id, 'is_active' => true]);
        $response = $this->getJson('/api/v1/categories/tree?view=menu')->assertOk();
        $response->assertJsonPath('tree.0.children.0.full_path', '/catalog/mebel/divany');
        $this->assertStringContainsString('Мягкая мебель', $response->getContent());
        $this->assertArrayNotHasKey('seo', $response->json('tree.0'));
        $this->assertArrayNotHasKey('parent', $response->json('tree.0.children.0'));
        $this->getJson('/api/v1/categories/tree')->assertOk()->assertJsonStructure(['tree' => [['seo', 'description']]]);
    }

    public function test_etag_revalidation_and_invalidation_after_edit(): void
    {
        $root = Category::factory()->create(['name' => 'До', 'parent_id' => null, 'is_active' => true]);
        $first = $this->getJson('/api/v1/categories/tree?view=menu')->assertOk();
        $this->assertStringContainsString('public', $first->headers->get('Cache-Control'));
        $this->assertStringContainsString('max-age=300', $first->headers->get('Cache-Control'));
        $this->assertStringNotContainsString('no-cache', $first->headers->get('Cache-Control'));
        $etag = $first->headers->get('ETag');
        $this->getJson('/api/v1/categories/tree?view=menu', ['If-None-Match' => $etag])->assertStatus(304)->assertContent('');
        $root->update(['name' => 'После']);
        $next = $this->getJson('/api/v1/categories/tree?view=menu', ['If-None-Match' => $etag])->assertOk();
        $next->assertJsonPath('tree.0.name', 'После');
        $this->assertNotSame($etag, $next->headers->get('ETag'));
    }

    public function test_rooms_menu_is_isolated_and_errors_are_not_publicly_cached(): void
    {
        Category::factory()->create(['name' => 'Не комната', 'parent_id' => null, 'is_active' => true]);
        $room = new Room;
        $room->forceFill(['name' => 'Спальня', 'slug' => 'spalnya', 'is_active' => true])->save();
        $this->getJson('/api/v1/rooms/tree?view=menu')->assertOk()->assertJsonCount(1, 'tree')->assertJsonPath('tree.0.name', 'Спальня');
        $error = $this->getJson('/api/v1/categories/tree?view=unknown')->assertStatus(422);
        $this->assertStringNotContainsString('public', $error->headers->get('Cache-Control'));
        $this->assertNull($error->headers->get('ETag'));
    }

    public function test_regions_have_conditional_cache_but_directory_failure_does_not(): void
    {
        $directory = \Mockery::mock(\App\Services\Address\AddressDirectoryClient::class);
        $directory->shouldReceive('options')->once()->with('regions', [])->andReturn([['externalId' => '48', 'name' => 'Липецкая область']]);
        $this->app->instance(\App\Services\Address\AddressDirectoryClient::class, $directory);
        $response = $this->getJson('/api/v1/address-directory/regions')->assertOk();
        $this->assertNotNull($response->headers->get('ETag'));
        $this->assertStringContainsString('public', $response->headers->get('Cache-Control'));
    }

    public function test_personal_prices_are_never_made_public_by_reference_middleware(): void
    {
        $response = $this->getJson('/api/v1/products')->assertOk();
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertNull($response->headers->get('ETag'));
    }

    public function test_directory_failures_are_not_cached(): void
    {
        $directory = \Mockery::mock(\App\Services\Address\AddressDirectoryClient::class);
        $directory->shouldReceive('options')->once()->andThrow(new \RuntimeException('Unavailable'));
        $this->app->instance(\App\Services\Address\AddressDirectoryClient::class, $directory);
        $response = $this->getJson('/api/v1/address-directory/regions')->assertStatus(503);
        $this->assertStringNotContainsString('public', $response->headers->get('Cache-Control'));
        $this->assertNull($response->headers->get('ETag'));
    }

    public function test_explicit_no_store_and_cookie_responses_are_preserved(): void
    {
        $middleware = new \App\Http\Middleware\PublicReferenceCache;
        $request = \Illuminate\Http\Request::create('/api/v1/categories/tree');
        foreach ([response()->json(['tree' => []])->header('Cache-Control', 'private, no-store'),
            response()->json(['tree' => []])->withCookie(cookie('test', 'private'))] as $response) {
            $result = $middleware->handle($request, fn () => $response);
            $this->assertStringNotContainsString('public', $result->headers->get('Cache-Control'));
            $this->assertNull($result->headers->get('ETag'));
        }
    }
}
