<?php

namespace Tests\Feature\Api;

use App\Models\Order\Order;
use App\Models\Order\OrderItem;
use App\Models\Product\Product;
use App\Models\User;
use App\Services\Order\OrderItemPreview;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

class OrderItemPreviewTest extends TestCase
{
    private function createLine(Order $order, Product $product, string $savedName): OrderItem
    {
        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'price' => 300,
            'total' => 600,
        ]);
        // Simulate an already-placed order whose product was later renamed.
        DB::table('order_items')->where('id', $item->id)->update([
            'name' => $savedName,
            'display_snapshot' => null,
        ]);

        return $item;
    }

    private function attachImage(Product $product): Media
    {
        return Media::query()->create([
            'model_type' => $product->getMorphClass(),
            'model_id' => $product->id,
            'collection_name' => 'images',
            'name' => 'Главное фото',
            'file_name' => 'cover.jpg',
            'mime_type' => 'image/jpeg',
            'disk' => 'public',
            'conversions_disk' => 'public',
            'size' => 100,
            'manipulations' => [],
            'custom_properties' => [],
            'generated_conversions' => ['thumb' => true],
            'responsive_images' => [],
        ]);
    }

    public function test_compact_and_last_order_return_saved_names_with_inherited_thumbnails(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);
        $parent = Product::factory()->create(['name' => 'Родитель']);
        $offer = Product::factory()->create([
            'name' => 'Текущее название предложения',
            'parent_product_id' => $parent->id,
        ]);
        $this->attachImage($parent);
        $line = $this->createLine($order, $offer, 'Название в момент покупки');
        $offer->update(['name' => 'Переименованное предложение']);

        $compact = $this->actingAs($user)->getJson('/api/v1/orders?view=compact');
        $compact->assertOk()
            ->assertJsonPath('data.0.items_count', 1)
            ->assertJsonPath('data.0.items.0.id', $line->id)
            ->assertJsonPath('data.0.items.0.name', 'Название в момент покупки')
            ->assertJsonPath('data.0.items.0.product.name', 'Название в момент покупки')
            ->assertJsonPath('data.0.items.0.quantity', 2)
            ->assertJsonPath('data.0.items_preview.0.name', 'Название в момент покупки')
            ->assertJsonMissingPath('data.0.items.0.product.stock')
            ->assertJsonMissingPath('data.0.items.0.product.price')
            ->assertJsonMissingPath('data.0.address')
            ->assertJsonMissingPath('data.0.status_history');
        $thumb = $compact->json('data.0.items.0.product.thumbnail');
        $this->assertIsString($thumb);
        $this->assertNotSame('', $thumb);
        $this->assertSame($thumb, $compact->json('data.0.items_preview.0.product.thumbnail'));

        $summary = $this->actingAs($user)->getJson('/api/v1/orders/summary');
        $summary->assertOk()
            ->assertJsonPath('last_order.id', $order->id)
            ->assertJsonPath('last_order.items.0.name', 'Название в момент покупки')
            ->assertJsonPath('last_order.items_preview.0.name', 'Название в момент покупки')
            ->assertJsonMissingPath('last_order.contact_email')
            ->assertJsonMissingPath('last_order.status_history');
        $this->assertSame($thumb, $summary->json('last_order.items.0.product.thumbnail'));
    }

    public function test_all_light_items_remain_available_but_items_preview_is_limited_to_three(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);
        for ($i = 1; $i <= 4; $i++) {
            $this->createLine($order, Product::factory()->create(), 'Сохранённая позиция '.$i);
        }
        $this->actingAs($user)->getJson('/api/v1/orders?view=compact')
            ->assertOk()
            ->assertJsonPath('data.0.items_count', 4)
            ->assertJsonCount(4, 'data.0.items')
            ->assertJsonCount(3, 'data.0.items_preview')
            ->assertJsonPath('data.0.items.3.name', 'Сохранённая позиция 4')
            ->assertJsonPath('data.0.items.0.product.thumbnail', null);
        $this->actingAs($user)->getJson('/api/v1/orders/summary')
            ->assertOk()
            ->assertJsonCount(4, 'last_order.items')
            ->assertJsonCount(3, 'last_order.items_preview');
    }

    public function test_preview_of_legacy_line_without_product_still_has_saved_name(): void
    {
        $item = new OrderItem([
            'product_id' => 99,
            'quantity' => 1,
            'price' => 200,
            'total' => 200,
        ]);
        $item->name = 'Историческая позиция';
        $item->setRelation('product', null);

        $preview = app(OrderItemPreview::class)->present($item);
        $this->assertSame('Историческая позиция', $preview['name']);
        $this->assertSame('Историческая позиция', $preview['product']['name']);
        $this->assertNull($preview['product']['image']);
        $this->assertNull($preview['product']['thumbnail']);
    }

    public function test_compact_query_count_with_product_media_does_not_grow_per_order(): void
    {
        $user = User::factory()->create();
        for ($i = 0; $i < 8; $i++) {
            $order = Order::factory()->create(['user_id' => $user->id]);
            $product = Product::factory()->create();
            $this->createLine($order, $product, 'Товар '.$i);
            if ($i === 0) {
                $this->attachImage($product);
            }
        }

        $this->actingAs($user);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->getJson('/api/v1/orders?view=compact&per_page=1')->assertOk();
        $small = count(DB::getQueryLog());
        DB::flushQueryLog();
        $this->actingAs($user)->getJson('/api/v1/orders?view=compact&per_page=8')
            ->assertOk()->assertJsonCount(8, 'data');
        $large = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual($small + 2, $large);
    }
}
