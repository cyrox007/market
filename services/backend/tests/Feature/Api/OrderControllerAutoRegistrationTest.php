<?php

namespace Tests\Feature\Api;

use App\Models\Mail\MailEvent;
use App\Models\Mail\MailEventTemplate;
use App\Models\Order\Order;
use App\Models\Payment\PaymentMethod;
use App\Models\Product\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class OrderControllerAutoRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        PaymentMethod::query()->firstOrCreate(
            ['code' => 'card'],
            ['name' => 'Оплата картой', 'is_active' => true, 'is_enabled' => true, 'gateway' => 'manual']
        );
    }

    private function addProductToCart(Product $product, int $quantity = 1): void
    {
        $this->postJson('/api/v1/cart', [
            'product_id' => $product->id,
            'quantity' => $quantity,
        ])->assertStatus(201);
    }

    public function test_guest_order_creates_user_and_logs_in(): void
    {
        // Создаем почтовые события для теста
        $orderCreatedEvent = MailEvent::factory()->create([
            'code' => 'order.created',
            'is_active' => true,
        ]);

        MailEventTemplate::factory()->create([
            'mail_event_id' => $orderCreatedEvent->id,
            'is_active' => true,
            'is_default' => true,
        ]);

        $userRegisteredEvent = MailEvent::factory()->create([
            'code' => 'user.registered',
            'is_active' => true,
        ]);

        MailEventTemplate::factory()->create([
            'mail_event_id' => $userRegisteredEvent->id,
            'is_active' => true,
            'is_default' => true,
        ]);

        $product = Product::factory()->create([
            'state' => 'active',
            'price' => 1000,
            'stock' => 10,
            'backorder' => false,
        ]);

        $this->addProductToCart($product, 1);

        $email = 'newuser@example.com';
        $name = 'New User';

        $response = $this->postJson('/api/v1/orders', [
            'contact_name' => $name,
            'contact_phone' => '+79991234567',
            'contact_email' => $email,
            'payment_method' => 'card',
            'delivery_type' => 'pickup',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'user_registered' => true,
            ]);

        // Проверяем, что пользователь создан
        $this->assertDatabaseHas('users', [
            'email' => $email,
            'name' => $name,
        ]);

        // Проверяем, что пользователь авторизован
        $user = User::where('email', $email)->first();
        $this->assertNotNull($user);
        $this->assertTrue(Auth::check());
        $this->assertEquals($user->id, Auth::id());

        // Проверяем, что заказ привязан к пользователю
        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'contact_email' => $email,
        ]);
    }

    public function test_guest_order_with_existing_email_binds_order_without_login(): void
    {
        // Создаем существующего пользователя
        $existingUser = User::factory()->create([
            'email' => 'existing@example.com',
            'name' => 'Existing User',
        ]);

        $orderCreatedEvent = MailEvent::factory()->create([
            'code' => 'order.created',
            'is_active' => true,
        ]);

        MailEventTemplate::factory()->create([
            'mail_event_id' => $orderCreatedEvent->id,
            'is_active' => true,
            'is_default' => true,
        ]);

        $product = Product::factory()->create([
            'state' => 'active',
            'price' => 1000,
            'stock' => 10,
            'backorder' => false,
        ]);

        $this->addProductToCart($product, 1);

        $response = $this->postJson('/api/v1/orders', [
            'contact_name' => 'New Name',
            'contact_phone' => '+79991234567',
            'contact_email' => $existingUser->email,
            'payment_method' => 'card',
            'delivery_type' => 'pickup',
        ]);

        $response->assertStatus(201);

        // Проверяем, что новый пользователь НЕ создан
        $this->assertEquals(1, User::where('email', $existingUser->email)->count());

        // Р-2: гость НЕ должен авторизоваться под чужим аккаунтом по одному лишь email
        $this->assertFalse(Auth::check());

        // Но заказ привязывается к аккаунту владельца email (он увидит его в своей истории)
        $this->assertDatabaseHas('orders', [
            'user_id' => $existingUser->id,
            'contact_email' => $existingUser->email,
        ]);
    }

    public function test_authenticated_user_order_does_not_create_new_user(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $orderCreatedEvent = MailEvent::factory()->create([
            'code' => 'order.created',
            'is_active' => true,
        ]);

        MailEventTemplate::factory()->create([
            'mail_event_id' => $orderCreatedEvent->id,
            'is_active' => true,
            'is_default' => true,
        ]);

        $product = Product::factory()->create([
            'state' => 'active',
            'price' => 1000,
            'stock' => 10,
            'backorder' => false,
        ]);

        $this->addProductToCart($product, 1);

        $response = $this->postJson('/api/v1/orders', [
            'contact_name' => $user->name,
            'contact_phone' => '+79991234567',
            'contact_email' => $user->email,
            'payment_method' => 'card',
            'delivery_type' => 'pickup',
        ]);

        $response->assertStatus(201)
            ->assertJsonMissing(['user_registered']);

        // Проверяем, что количество пользователей не изменилось
        $this->assertEquals(1, User::count());

        // Проверяем, что заказ привязан к текущему пользователю
        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
        ]);
    }

    public function test_guest_order_with_phone_of_another_user_registers_without_phone(): void
    {
        // Телефон уже занят другим аккаунтом (иной email).
        User::factory()->create(['phone' => '+79991234567', 'email' => 'owner@example.com']);

        $orderCreatedEvent = MailEvent::factory()->create(['code' => 'order.created', 'is_active' => true]);
        MailEventTemplate::factory()->create([
            'mail_event_id' => $orderCreatedEvent->id,
            'is_active' => true,
            'is_default' => true,
        ]);
        $userRegisteredEvent = MailEvent::factory()->create(['code' => 'user.registered', 'is_active' => true]);
        MailEventTemplate::factory()->create([
            'mail_event_id' => $userRegisteredEvent->id,
            'is_active' => true,
            'is_default' => true,
        ]);

        $product = Product::factory()->create([
            'state' => 'active',
            'price' => 1000,
            'stock' => 10,
            'backorder' => false,
        ]);
        $this->addProductToCart($product, 1);

        $response = $this->postJson('/api/v1/orders', [
            'contact_name' => 'Guest With Taken Phone',
            'contact_phone' => '89991234567', // тот же номер в ином формате
            'contact_email' => 'guest@example.com',
            'payment_method' => 'card',
            'delivery_type' => 'pickup',
        ]);

        // Заказ проходит, коллизии уникальности нет.
        $response->assertStatus(201);

        // Новый юзер создан, но телефон ему не присвоен (остался за владельцем).
        $newUser = User::where('email', 'guest@example.com')->first();
        $this->assertNotNull($newUser);
        $this->assertNull($newUser->phone);

        // В заказе телефон сохранён и нормализован — уйдёт в 1С в едином формате.
        $this->assertDatabaseHas('orders', [
            'user_id' => $newUser->id,
            'contact_phone' => '+79991234567',
        ]);
    }
}
