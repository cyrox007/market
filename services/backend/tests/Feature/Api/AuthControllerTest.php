<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone' => '+79991234567',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email', 'phone'],
            ])
            ->assertJsonMissing(['token']); // Токен больше не возвращается

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'name' => 'Test User',
        ]);

        // Проверяем, что пользователь аутентифицирован через сессию
        $this->assertTrue(Auth::check());
    }

    public function test_register_requires_phone(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'No Phone',
            'email' => 'nophone@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['phone']);
    }

    public function test_register_rejects_invalid_phone(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Bad Phone',
            'email' => 'badphone@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone' => '12345',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['phone']);
    }

    public function test_register_normalizes_phone_to_canonical_form(): void
    {
        // Ввод в «человеческом» формате приводится к канону +7XXXXXXXXXX.
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Human Phone',
            'email' => 'human@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone' => '8 (999) 123-45-67',
        ]);

        $response->assertStatus(201)
            ->assertJson(['user' => ['phone' => '+79991234567']]);

        $this->assertDatabaseHas('users', [
            'email' => 'human@example.com',
            'phone' => '+79991234567',
        ]);
    }

    public function test_register_rejects_duplicate_phone_across_formats(): void
    {
        User::factory()->create(['phone' => '+79991234567']);

        // Тот же номер в ином формате — после нормализации это дубль.
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Dup Phone',
            'email' => 'dup@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone' => '89991234567',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['phone']);
    }

    public function test_profile_update_rejects_phone_taken_by_another_user(): void
    {
        User::factory()->create(['phone' => '+79991112233']);
        $user = User::factory()->create(['phone' => '+79994445566']);

        $response = $this->actingAs($user, 'web')
            ->putJson('/api/v1/auth/profile', [
                'phone' => '+79991112233',
            ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['phone']);
    }

    public function test_user_can_login(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email', 'phone'],
            ])
            ->assertJsonMissing(['token']); // Токен больше не возвращается

        // Проверяем, что пользователь аутентифицирован через сессию
        $this->assertTrue(Auth::check());
        $this->assertEquals($user->id, Auth::id());
    }

    public function test_user_cannot_login_with_wrong_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(422);
    }

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->create();

        // Аутентифицируем пользователя через сессию (используем web guard для session-based auth)
        $response = $this->actingAs($user, 'web')
            ->postJson('/api/v1/auth/logout');

        $response->assertStatus(200);
        $this->getJson('/api/v1/auth/me')->assertStatus(401);
    }

    public function test_authenticated_user_can_get_profile(): void
    {
        $user = User::factory()->create();

        // Аутентифицируем пользователя через сессию (используем web guard для session-based auth)
        $response = $this->actingAs($user, 'web')
            ->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email', 'phone'],
            ])
            ->assertJson([
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
            ]);
    }

    public function test_user_can_update_profile(): void
    {
        $user = User::factory()->create();

        // Аутентифицируем пользователя через сессию (используем web guard для session-based auth)
        $response = $this->actingAs($user, 'web')
            ->putJson('/api/v1/auth/profile', [
                'name' => 'Updated Name',
                'phone' => '+79991234567',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'user' => [
                    'name' => 'Updated Name',
                    'phone' => '+79991234567',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
        ]);
    }

    public function test_unauthenticated_user_cannot_access_protected_routes(): void
    {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertStatus(401);
    }

    public function test_unauthenticated_user_cannot_logout(): void
    {
        $response = $this->postJson('/api/v1/auth/logout');

        $response->assertStatus(401);
    }

    public function test_cookie_based_authentication_persists_across_requests(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ]);

        // Логинимся
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $loginResponse->assertStatus(200);

        // Делаем запрос к защищенному маршруту без явной аутентификации
        // Сессия должна сохраниться
        $meResponse = $this->getJson('/api/v1/auth/me');

        $meResponse->assertStatus(200)
            ->assertJson([
                'user' => [
                    'id' => $user->id,
                    'email' => 'test@example.com',
                ],
            ]);
    }

    public function test_logout_clears_session(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ]);

        // Логинимся
        $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ])->assertStatus(200);

        // Проверяем доступ
        $this->getJson('/api/v1/auth/me')->assertStatus(200);

        // Выходим
        $this->postJson('/api/v1/auth/logout')->assertStatus(200);

        // Проверяем, что доступ больше не работает
        $this->getJson('/api/v1/auth/me')->assertStatus(401);
    }

    public function test_user_can_update_profile_with_name_and_phone(): void
    {
        $user = User::factory()->create([
            'name' => 'Old Name',
            'phone' => null,
        ]);

        $response = $this->actingAs($user, 'web')
            ->putJson('/api/v1/auth/profile', [
                'name' => 'New Name',
                'phone' => '+79991234567',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'user' => [
                    'name' => 'New Name',
                    'phone' => '+79991234567',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'New Name',
            'phone' => '+79991234567',
        ]);
    }

    public function test_user_can_update_only_name(): void
    {
        $user = User::factory()->create([
            'name' => 'Old Name',
            'phone' => '+79991234567',
        ]);

        $response = $this->actingAs($user, 'web')
            ->putJson('/api/v1/auth/profile', [
                'name' => 'New Name',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'user' => [
                    'name' => 'New Name',
                ],
            ]);

        // Телефон должен остаться прежним
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'New Name',
            'phone' => '+79991234567',
        ]);
    }

    public function test_user_can_update_only_phone(): void
    {
        $user = User::factory()->create([
            'name' => 'Test User',
            'phone' => null,
        ]);

        $response = $this->actingAs($user, 'web')
            ->putJson('/api/v1/auth/profile', [
                'phone' => '+79991234567',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'user' => [
                    'phone' => '+79991234567',
                ],
            ]);

        // Имя должно остаться прежним
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Test User',
            'phone' => '+79991234567',
        ]);
    }

    public function test_user_cannot_clear_phone(): void
    {
        // Телефон — обязательный идентификатор пользователя, обнулять его нельзя.
        $user = User::factory()->create([
            'name' => 'Test User',
            'phone' => '+79991234567',
        ]);

        $response = $this->actingAs($user, 'web')
            ->putJson('/api/v1/auth/profile', [
                'phone' => null,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'phone' => '+79991234567',
        ]);
    }

    public function test_profile_update_validates_name_max_length(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'web')
            ->putJson('/api/v1/auth/profile', [
                'name' => str_repeat('a', 256), // Превышает максимум 255
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_profile_update_validates_phone_max_length(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'web')
            ->putJson('/api/v1/auth/profile', [
                'phone' => str_repeat('1', 51), // Превышает максимум 50
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }
}
