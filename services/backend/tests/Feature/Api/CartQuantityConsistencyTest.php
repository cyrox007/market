<?php

namespace Tests\Feature\Api;

use App\Models\Product\Product;
use App\Services\Inventory\WarehouseStockResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;
use Vanilo\Cart\Events\CartUpdated;

/**
 * PUT /cart/{itemId}: конечное количество с проверкой остатка, id позиции стабилен.
 */
class CartQuantityConsistencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Статический кэш остатков переживает тесты, а ID товаров между ними повторяются
        WarehouseStockResolver::clearCache();
    }

    public function test_update_keeps_item_id_and_sets_final_quantity(): void
    {
        $itemId = $this->addToCart($this->product(stock: 20));

        foreach ([2, 5, 3] as $quantity) {
            $this->putJson("/api/v1/cart/{$itemId}", ['quantity' => $quantity])
                ->assertOk()
                ->assertJsonPath('item.id', $itemId)
                ->assertJsonPath('item.quantity', $quantity)
                ->assertJsonPath('updated_quantity', $quantity)
                ->assertJsonPath('was_adjusted', false)
                ->assertJsonPath('limit_reason', null);
        }

        $this->assertSame([[$itemId, 3]], $this->cartItems());
    }

    public function test_update_is_limited_by_stock(): void
    {
        $itemId = $this->addToCart($this->product(stock: 4));

        $this->putJson("/api/v1/cart/{$itemId}", ['quantity' => 10])
            ->assertOk()
            ->assertJsonPath('item.id', $itemId)
            ->assertJsonPath('requested_quantity', 10)
            ->assertJsonPath('updated_quantity', 4)
            ->assertJsonPath('was_adjusted', true)
            ->assertJsonPath('limit_reason', 'stock');
    }

    public function test_update_is_limited_by_max_per_order(): void
    {
        $itemId = $this->addToCart($this->product(stock: 0, backorder: true));

        $this->putJson("/api/v1/cart/{$itemId}", ['quantity' => 150])
            ->assertOk()
            ->assertJsonPath('updated_quantity', 100)
            ->assertJsonPath('limit_reason', 'max_per_order');
    }

    public function test_update_of_removed_item_returns_not_found(): void
    {
        $itemId = $this->addToCart($this->product(stock: 20));
        $this->deleteJson("/api/v1/cart/{$itemId}")->assertOk();

        $this->putJson("/api/v1/cart/{$itemId}", ['quantity' => 2])->assertNotFound();
    }

    public function test_adding_existing_product_increases_same_item(): void
    {
        $product = $this->product(stock: 5);
        $itemId = $this->addToCart($product);

        $this->postJson('/api/v1/cart', ['product_id' => $product->id, 'quantity' => 2])
            ->assertCreated()
            ->assertJsonPath('item.id', $itemId)
            ->assertJsonPath('item.quantity', 3);

        $this->postJson('/api/v1/cart', ['product_id' => $product->id, 'quantity' => 5])
            ->assertCreated()
            ->assertJsonPath('item.quantity', 5)
            ->assertJsonPath('added_quantity', 2)
            ->assertJsonPath('limit_reason', 'stock');

        $this->assertSame([[$itemId, 5]], $this->cartItems());
    }

    public function test_adding_product_created_by_parallel_request_respects_stock(): void
    {
        $firstItemId = $this->addToCart($this->product(stock: 20));
        $product = $this->product(stock: 5);

        // Позицию создал параллельный запрос: в снимке корзины в памяти её нет
        $cartId = DB::table('cart_items')->where('id', $firstItemId)->value('cart_id');
        $parallelItemId = DB::table('cart_items')->insertGetId([
            'cart_id' => $cartId,
            'product_type' => $product->morphTypeName(),
            'product_id' => $product->id,
            'quantity' => 4,
            'price' => 1000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson('/api/v1/cart', ['product_id' => $product->id, 'quantity' => 3])
            ->assertCreated()
            ->assertJsonPath('item.id', $parallelItemId)
            ->assertJsonPath('item.quantity', 5)
            ->assertJsonPath('added_quantity', 1)
            ->assertJsonPath('limit_reason', 'stock');

        $this->assertSame(1, DB::table('cart_items')->where('product_id', $product->id)->count());
    }

    public function test_in_place_changes_notify_cart_listeners(): void
    {
        $product = $this->product(stock: 20);
        $itemId = $this->addToCart($product);
        Event::fake([CartUpdated::class]);

        $this->putJson("/api/v1/cart/{$itemId}", ['quantity' => 4])->assertOk();
        $this->postJson('/api/v1/cart', ['product_id' => $product->id, 'quantity' => 1])->assertCreated();

        Event::assertDispatchedTimes(CartUpdated::class, 2);
    }

    protected function product(int $stock, bool $backorder = false): Product
    {
        return Product::factory()->create([
            'state' => 'active',
            'price' => 1000,
            'stock' => $stock,
            'backorder' => $backorder,
            'parent_product_id' => null,
        ]);
    }

    protected function addToCart(Product $product): int
    {
        return (int) $this->postJson('/api/v1/cart', ['product_id' => $product->id, 'quantity' => 1])
            ->assertCreated()
            ->json('item.id');
    }

    /**
     * @return list<array{0: int, 1: int}>
     */
    protected function cartItems(): array
    {
        return collect($this->getJson('/api/v1/cart')->json('items'))
            ->map(fn (array $item) => [(int) $item['id'], (int) $item['quantity']])
            ->all();
    }
}
