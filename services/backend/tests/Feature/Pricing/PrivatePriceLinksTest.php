<?php

namespace Tests\Feature\Pricing;

use App\Models\Pricing\PrivatePriceLink;
use App\Models\Product\Product;
use Tests\TestCase;

class PrivatePriceLinksTest extends TestCase
{
    public function test_only_holder_gets_private_price_and_expired_or_revoked_link_stops_working(): void
    {
        $sku = Product::factory()->create(['price' => 1500, 'original_price' => null]);
        $other = Product::factory()->create(['price' => 1500]);
        $link = PrivatePriceLink::create(['name' => 'Avito chair', 'product_id' => $sku->id, 'price' => 1000, 'expires_at' => now()->addDay()]);
        $token = $link->rotateToken();
        $this->assertNotSame($token, $link->token_hash);
        $this->getJson('/api/v1/products/'.$sku->slug)->assertOk()->assertJsonPath('product.price', 1500);
        $this->postJson('/api/v1/private-offers/activate', ['token' => $token])->assertOk();
        $this->getJson('/api/v1/products/'.$sku->slug)->assertOk()->assertJsonPath('product.price', 1500);
        $this->withHeader('X-Private-Price-Context', '1');
        $this->getJson('/api/v1/products/'.$sku->slug)->assertOk()->assertJsonPath('product.price', 1000);
        $list = $this->getJson('/api/v1/products?price_max=1100')->assertOk();
        $this->assertContains($sku->id, collect($list->json('data'))->pluck('id')->all());
        $this->assertNotContains($other->id, collect($list->json('data'))->pluck('id')->all());
        $this->getJson('/api/v1/products/'.$other->slug)->assertOk()->assertJsonPath('product.price', 1500);
        $this->assertEquals(1500, $sku->fresh()->price);
        $link->update(['is_active' => false]);
        $this->getJson('/api/v1/products/'.$sku->slug)->assertOk()->assertJsonPath('product.price', 1500);
        $this->postJson('/api/v1/private-offers/activate', ['token' => $token])->assertNotFound();
    }

    public function test_token_cannot_be_guessed_or_change_price_and_expiry_is_enforced(): void
    {
        $sku = Product::factory()->create(['price' => 1500]);
        $link = PrivatePriceLink::create(['name' => 'Avito', 'product_id' => $sku->id, 'price' => 1000, 'expires_at' => now()->addMinute()]);
        $token = $link->rotateToken();
        $this->postJson('/api/v1/private-offers/activate', ['token' => str_repeat('a', 64), 'price' => 1])->assertNotFound();
        $this->postJson('/api/v1/private-offers/activate', ['token' => $token, 'price' => 1])->assertOk()->assertJsonPath('price', 1000);
        $this->travel(1)->minutes();
        $this->postJson('/api/v1/private-offers/activate', ['token' => $token])->assertNotFound();
        $this->getJson('/api/v1/products/'.$sku->slug)->assertOk()->assertJsonPath('product.price', 1500);
    }
}
