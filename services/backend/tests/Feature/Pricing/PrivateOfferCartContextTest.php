<?php
namespace Tests\Feature\Pricing;

use App\Models\Product\Product;
use App\Models\Pricing\PrivatePriceLink;
use App\Models\Inventory\Warehouse;
use Tests\TestCase;

class PrivateOfferCartContextTest extends TestCase
{
    public function test_plain_link_and_offer_link_keep_separate_cart_prices_for_the_same_sku(): void
    {
        $sku = Product::factory()->create(['price' => 1500, 'stock' => 10]);
        $warehouse = Warehouse::create(['name' => 'Central', 'is_active' => true]);
        $warehouse->productStocks()->create(['product_id' => $sku->id, 'quantity' => 10]);
        $link = PrivatePriceLink::create(['name' => 'Avito', 'product_id' => $sku->id, 'price' => 1000, 'expires_at' => now()->addDay()]);
        $this->postJson('/api/v1/private-offers/activate', ['token' => $link->rotateToken()])->assertOk();
        $this->withHeader('X-Private-Price-Context', (string) $link->id);
        $this->getJson('/api/v1/products/'.$sku->slug)->assertOk()->assertJsonPath('product.price', 1000);
        $discount = $this->postJson('/api/v1/cart', ['product_id' => $sku->id, 'quantity' => 1])->assertCreated()->assertJsonPath('item.price', 1000);
        $this->withHeader('X-Private-Price-Context', '');
        $this->getJson('/api/v1/products/'.$sku->slug)->assertOk()->assertJsonPath('product.price', 1500);
        $normal = $this->postJson('/api/v1/cart', ['product_id' => $sku->id, 'quantity' => 1])->assertCreated()->assertJsonPath('item.price', 1500);
        $this->assertNotSame($discount->json('item.id'), $normal->json('item.id'));
        $this->getJson('/api/v1/cart')->assertOk()->assertJsonCount(2, 'items')->assertJsonPath('subtotal', 2500);
        $pricedItems = app(\App\Services\Pricing\PrivateOfferAccess::class)->pricedItems(\Vanilo\Cart\Facades\Cart::getItems(), null);
        $this->assertSame([1000.0, 1500.0], array_column($pricedItems, 'price'));
        $this->assertDatabaseHas('cart_stock_reservations', ['product_id' => $sku->id, 'quantity' => 2]);
        $this->deleteJson('/api/v1/cart/'.$normal->json('item.id'))->assertOk();
        $this->getJson('/api/v1/cart')->assertOk()->assertJsonPath('subtotal', 1000);
        $this->assertDatabaseHas('cart_stock_reservations', ['product_id' => $sku->id, 'quantity' => 1]);
    }
}
