<?php

namespace Tests\Feature\Api;

use App\Models\Address\Address;
use App\Models\Order\Order;
use App\Models\Payment\PaymentMethod;
use App\Models\Product\Product;
use App\Models\Shipping\DeliveryHandlingType;
use App\Models\Shipping\ShippingLocation;
use App\Models\User;
use App\Models\Inventory\Warehouse;
use Illuminate\Support\Facades\Http;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderControllerTest extends TestCase
{
    use RefreshDatabase;

    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake(function ($request) {
            $id = rawurldecode(basename(parse_url($request->url(), PHP_URL_PATH)));
            $region = ['kind' => 'region', 'externalId' => 'region48', 'name' => 'Область'];
            $city = ['kind' => 'locality', 'externalId' => 'city48', 'name' => 'Москва'];
            $street = ['kind' => 'street', 'externalId' => 'street48', 'name' => 'Ленина'];
            $target = $id === 'region48' ? $region : ['kind' => 'building', 'source' => 'gar', 'externalId' => 'house', 'name' => '1'];
            return Http::response(['target' => $target, 'path' => [$region, $city, $street], 'label' => 'Москва, Ленина, д. 1']);
        });
        $this->warehouse = Warehouse::create(['name' => 'Test warehouse', 'address' => 'Москва, Ленина, д. 1', 'pickup_enabled' => true]);
        $this->warehouse->deliveryProfiles()->create(['name' => 'Москва', 'base_price' => 500, 'directory_territories' => [['region_id' => 'region48']], 'service_tariffs' => []]);

        PaymentMethod::query()->firstOrCreate(
            ['code' => 'card'],
            ['name' => 'Оплата картой', 'is_active' => true, 'is_enabled' => true, 'gateway' => 'manual']
        );
    }

    private function addProductToCart(Product $product, int $quantity = 1): void
    {
        $this->warehouse->productStocks()->updateOrCreate(['product_id' => $product->id], ['quantity' => max(10, (int) $product->stock)]);
        $this->postJson('/api/v1/cart', [
            'product_id' => $product->id,
            'quantity' => $quantity,
        ])->assertStatus(201);
    }

    private function makeDeliveryScenario(): array
    {
        /** @var ShippingLocation $location */
        $location = ShippingLocation::factory()->create([
            'type' => 'locality',
            'name' => 'Москва',
            'slug' => 'moscow',
            'delivery_price' => 500,
            'free_delivery_threshold' => null,
            // В тестах отключаем ограничения доступности доставки, чтобы не ловить случайные 422
            'min_order_amount' => null,
            'max_order_weight' => null,
            'max_order_volume' => null,
            'requires_assembly' => true,
            'assembly_price' => 1000,
            'is_active' => true,
        ]);

        /** @var DeliveryHandlingType $handling */
        $handling = DeliveryHandlingType::factory()->create([
            'name' => 'Ручной подъем',
            'code' => 'manual',
            'requires_floor' => true,
            'is_active' => true,
        ]);

        $location->deliveryHandlingTypes()->attach($handling->id, [
            'base_price' => 200,
            'is_active' => true,
        ]);

        $this->warehouse->deliveryProfiles()->first()->update(['service_tariffs' => ['assembly_price' => 1000, 'handling' => [['id' => $handling->id, 'base_price' => 200]]]]);
        return [$location, $handling, $this->warehouse];
    }


    public function test_sber_registration_failure_preserves_order_and_checkout_key_after_commit(): void
    {
        $method = PaymentMethod::create(['code' => 'sber-checkout', 'name' => 'Sber', 'gateway' => 'sberbank_acquiring',
            'is_active' => true, 'is_enabled' => true]);
        $user = User::factory()->create();
        $this->actingAs($user, 'web');
        $product = Product::factory()->create(['state' => 'active', 'price' => 1000, 'stock' => 10, 'backorder' => false]);
        $this->addProductToCart($product);
        $level = \Illuminate\Support\Facades\DB::transactionLevel();
        $client = \Mockery::mock(\App\Services\Payment\SberbankAcquiringClient::class);
        $observed = [];
        $client->shouldReceive('registerOrder')->once()->andReturnUsing(function ($order, $amount, $return, $fail, $number, $key) use (&$observed) {
            $observed = [
                'level' => \Illuminate\Support\Facades\DB::transactionLevel(),
                'committed' => \Illuminate\Support\Facades\DB::table('checkout_attempts')->where('order_id', $order->id)->exists(),
                'intent' => $order->getLatestPayment()->data['sberbank_registration'],
                'number' => $number, 'key' => $key,
            ];
            throw new \RuntimeException('Bank response lost');
        });
        $client->shouldReceive('getOrderStatusExtended')->once()->andReturn([]);
        $this->app->instance(\App\Services\Payment\SberbankAcquiringClient::class, $client);
        $body = ['contact_name' => 'Test User', 'contact_phone' => '+79991234567',
            'contact_email' => $user->email, 'payment_method' => $method->code,
            'delivery_type' => 'pickup', 'warehouse_id' => $this->warehouse->id];
        $headers = ['Idempotency-Key' => 'sber-committed-checkout-test', 'Authorization' => 'Bearer '.$user->createToken('sber')->plainTextToken];
        $response = $this->postJson('/api/v1/orders', $body, $headers)->assertCreated()
            ->assertJsonPath('payment_registration_pending', true);
        $id = $response->json('order.id');
        $this->assertSame($level, $observed['level']);
        $this->assertTrue($observed['committed']);
        $this->assertSame('submitted', $observed['intent']['state']);
        $this->assertSame($observed['number'], $observed['intent']['number']);
        $this->assertSame($observed['key'], $observed['intent']['key']);
        $this->postJson('/api/v1/orders', $body, $headers)->assertCreated()
            ->assertJsonPath('order.id', $id)->assertJsonPath('payment_registration_pending', true);
        $this->assertCount(1, Order::where('payment_method', $method->code)->get());
        $this->assertCount(1, Order::findOrFail($id)->payments()->get());
    }

    public function test_authenticated_user_can_list_orders(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        Order::factory()->count(3)->create(['user_id' => $user->id]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/orders');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_guest_can_create_pickup_order_from_cart(): void
    {
        $product = Product::factory()->create([
            'state' => 'active',
            'price' => 1000,
            'stock' => 10,
            'backorder' => false,
        ]);

        $this->addProductToCart($product, 2);

        $response = $this->postJson('/api/v1/orders', [
            'contact_name' => 'Test User',
            'contact_phone' => '+79991234567',
            'contact_email' => 'test@example.com',
            'payment_method' => 'card',
            'delivery_type' => 'pickup',
            'warehouse_id' => $this->warehouse->id,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'order' => [
                    'id',
                    'number',
                    'status',
                    'delivery_type',
                    'delivery_cost',
                    'assembly_cost',
                    'total',
                    'items',
                ],
                'message',
            ]);

        $this->assertDatabaseHas('orders', [
            'contact_email' => 'test@example.com',
            'delivery_type' => 'pickup',
        ]);

        // Корзина должна быть очищена после создания заказа
        $this->getJson('/api/v1/cart')->assertJsonPath('is_empty', true);
        // Должна быть создана запись в истории статусов
        $this->assertDatabaseHas('order_status_history', [
            'status' => 'manager_processing',
            'comment' => 'Заказ создан.',
        ]);
    }

    public function test_guest_can_create_delivery_order_and_delivery_cost_includes_handling_but_not_assembly(): void
    {
        [$location, $handling, $shippingMethod] = $this->makeDeliveryScenario();

        $product = Product::factory()->create([
            'state' => 'active',
            'price' => 1000,
            'stock' => 10,
            'backorder' => false,
        ]);

        // subtotal=2000
        $this->addProductToCart($product, 2);

        $response = $this->postJson('/api/v1/orders', [
            'contact_name' => 'Test User',
            'contact_phone' => '+79991234567',
            'contact_email' => 'test@example.com',
            'payment_method' => 'card',
            'delivery_type' => 'delivery',
            'directory_external_id' => 'house',
            'delivery_handling_type_id' => $handling->id,
            'delivery_floor' => 4,
            'requires_assembly' => false,
            'delivery_details' => ['apartment' => '10'],
        ]);

        $response->assertStatus(201);

        $this->assertSame('delivery', $response->json('order.delivery_type'));
        $this->assertSame(700.0, (float) $response->json('order.delivery_cost')); // 500 + 200
        $this->assertSame(0.0, (float) $response->json('order.assembly_cost'));
        $this->assertNotNull($response->json('order.address.id'));
    }

    public function test_delivery_order_with_first_shipping_method_succeeds(): void
    {
        $product = Product::factory()->create(['stock' => 10, 'price' => 1000]);
        $this->addProductToCart($product);
        $quote = $this->postJson('/api/v1/shipping/quote', ['directory_external_id' => 'house'])->assertOk();
        $this->postJson('/api/v1/orders', [
            'contact_name' => 'Test', 'contact_phone' => '+79991234567', 'contact_email' => 'quote@example.test',
            'payment_method' => 'card', 'delivery_type' => 'delivery', 'directory_external_id' => 'house',
            'warehouse_id' => $quote->json('selected.warehouse_id'),
        ])->assertCreated()->assertJsonPath('order.delivery_cost', 500);
    }

    public function test_delivery_order_requires_shipping_location(): void
    {
        $product = Product::factory()->create([
            'state' => 'active',
            'price' => 1000,
            'stock' => 10,
            'backorder' => false,
        ]);
        $this->addProductToCart($product, 1);

        $response = $this->postJson('/api/v1/orders', [
            'contact_name' => 'Test User',
            'contact_phone' => '+79991234567',
            'contact_email' => 'test@example.com',
            'payment_method' => 'card',
            'delivery_type' => 'delivery',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('directory_external_id');
    }

    public function test_delivery_order_requires_floor_when_handling_type_requires_floor(): void
    {
        [$location, $handling] = $this->makeDeliveryScenario();

        $product = Product::factory()->create([
            'state' => 'active',
            'price' => 1000,
            'stock' => 10,
            'backorder' => false,
        ]);
        $this->addProductToCart($product, 1);

        $response = $this->postJson('/api/v1/orders', [
            'contact_name' => 'Test User',
            'contact_phone' => '+79991234567',
            'contact_email' => 'test@example.com',
            'payment_method' => 'card',
            'delivery_type' => 'delivery',
            'directory_external_id' => 'house',
            'delivery_handling_type_id' => $handling->id,
            // delivery_floor отсутствует
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('delivery_handling_type_id');
    }

    public function test_can_get_order_details(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $order = Order::factory()->create(['user_id' => $user->id]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/orders/{$order->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'order' => [
                    'id',
                    'number',
                    'status',
                    'items',
                ],
            ]);
    }

    public function test_can_cancel_order(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => 'new',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/orders/{$order->id}/cancel", [
                'comment' => 'Не нужен',
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_can_repeat_order(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $order = Order::factory()->create(['user_id' => $user->id]);
        $product = Product::factory()->create([
            'state' => 'active',
            'price' => 1000,
            'stock' => 10,
            'backorder' => false,
        ]);
        \App\Models\Order\OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 1000,
            'total' => 1000,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/orders/{$order->id}/repeat");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'added_items',
            ]);
    }

    public function test_authenticated_user_order_creates_address_in_profile(): void
    {
        $user = User::factory()->create();
        $location = ShippingLocation::factory()->create([
            'type' => 'locality',
            'is_active' => true,
            'min_order_amount' => null,
            'max_order_weight' => null,
            'max_order_volume' => null,
        ]);

        $product = Product::factory()->create([
            'state' => 'active',
            'price' => 1000,
            'stock' => 10,
            'backorder' => false,
        ]);

        $this->addProductToCart($product, 1);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/orders', [
                'contact_name' => 'Test User',
                'contact_phone' => '+79991234567',
                'contact_email' => 'test@example.com',
                'payment_method' => 'card',
                'delivery_type' => 'delivery',
                'directory_external_id' => 'house',
                'delivery_details' => ['apartment' => '10'],
            ]);

        $response->assertStatus(201);

        // Проверяем, что адрес создан в профиле пользователя
        $this->assertDatabaseHas('user_addresses', [
            'user_id' => $user->id,
            'city' => 'Москва',
            'street' => 'Ленина',
            'house' => '1',
            'apartment' => '10',
            'directory_external_id' => 'house',
        ]);
    }

    public function test_guest_order_creates_address_without_user_id(): void
    {
        $location = ShippingLocation::factory()->create([
            'type' => 'locality',
            'is_active' => true,
            'min_order_amount' => null,
            'max_order_weight' => null,
            'max_order_volume' => null,
        ]);

        $product = Product::factory()->create([
            'state' => 'active',
            'price' => 1000,
            'stock' => 10,
            'backorder' => false,
        ]);

        $this->addProductToCart($product, 1);

        $response = $this->postJson('/api/v1/orders', [
            'contact_name' => 'Test User',
            'contact_phone' => '+79991234567',
            'contact_email' => 'test@example.com',
            'payment_method' => 'card',
            'delivery_type' => 'delivery',
            'directory_external_id' => 'house',
            'delivery_details' => ['apartment' => '10'],
        ]);

        $response->assertStatus(201);

        // В актуальном сценарии гостевой заказ создает/привязывает пользователя автоматически.
        $this->assertDatabaseHas('user_addresses', [
            'city' => 'Москва',
            'street' => 'Ленина',
            'house' => '1',
        ]);
    }

    public function test_order_contact_data_matches_user_profile(): void
    {
        $user = User::factory()->create([
            'name' => 'John Doe',
            'phone' => '+79991234567',
            'email' => 'john@example.com',
        ]);

        $product = Product::factory()->create([
            'state' => 'active',
            'price' => 1000,
            'stock' => 10,
            'backorder' => false,
        ]);

        $this->addProductToCart($product, 1);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/orders', [
                'contact_name' => 'John Doe',
                'contact_phone' => '+79991234567',
                'contact_email' => 'john@example.com',
                'payment_method' => 'card',
                'delivery_type' => 'pickup',
            'warehouse_id' => $this->warehouse->id,
            ]);

        $response->assertStatus(201);

        // Проверяем, что данные заказа соответствуют профилю
        $order = $response->json('order');
        $this->assertEquals($user->name, $order['contact_name']);
        $this->assertEquals($user->phone, $order['contact_phone']);
        $this->assertEquals($user->email, $order['contact_email']);
    }

    public function test_delivery_rejects_shipping_method_not_available_for_location(): void
    {
        $product = Product::factory()->create(['stock' => 10]);
        $this->addProductToCart($product);
        $this->postJson('/api/v1/orders', [
            'contact_name' => 'Test', 'contact_phone' => '+79991234567', 'contact_email' => 'old@example.test',
            'payment_method' => 'card', 'delivery_type' => 'delivery', 'directory_external_id' => 'house',
            'shipping_method_id' => 999,
        ])->assertUnprocessable()->assertJsonValidationErrors('shipping_method_id');
    }

    public function test_pickup_rejects_client_delivery_cost_override(): void
    {
        $product = Product::factory()->create([
            'state' => 'active',
            'price' => 1000,
            'stock' => 10,
            'backorder' => false,
        ]);
        $this->addProductToCart($product, 1);

        $response = $this->postJson('/api/v1/orders', [
            'contact_name' => 'Test User',
            'contact_phone' => '+79991234567',
            'contact_email' => 'pickup-cost@example.com',
            'payment_method' => 'card',
            'delivery_type' => 'pickup',
            'warehouse_id' => $this->warehouse->id,
            'delivery_cost' => 999,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('delivery_cost');
    }

    public function test_customer_cannot_choose_unconfirmed_delivery_date_or_time(): void
    {
        $product = Product::factory()->create(['state' => 'active', 'price' => 1000, 'stock' => 10]);
        $this->addProductToCart($product);
        $payload = [
            'contact_name' => 'Покупатель',
            'contact_phone' => '+79991234567',
            'contact_email' => 'no-slots@example.test',
            'payment_method' => 'card',
            'delivery_type' => 'delivery',
            'directory_external_id' => 'house',
        ];

        $this->postJson('/api/v1/orders', [...$payload, 'delivery_date' => now()->addDays(7)->toDateString()])
            ->assertUnprocessable()->assertJsonValidationErrors('delivery_date');
        $this->postJson('/api/v1/orders', [...$payload, 'delivery_time' => '14:00 - 17:00'])
            ->assertUnprocessable()->assertJsonValidationErrors('delivery_time');

        $response = $this->postJson('/api/v1/orders', $payload)->assertCreated();
        $this->assertNull($response->json('order.delivery_date'));
        $this->assertNull($response->json('order.delivery_time'));
    }
}
