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

    public function test_registration_accepts_structured_name_with_compound_parts(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'first_name' => 'Анна Мария',
            'last_name' => 'де ла Крус',
            'patronymic' => null,
            'email' => 'anna@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone' => '+79991234567',
        ]);
        $response->assertCreated()
            ->assertJsonPath('user.name', 'де ла Крус Анна Мария')
            ->assertJsonPath('user.first_name', 'Анна Мария')
            ->assertJsonPath('user.last_name', 'де ла Крус')
            ->assertJsonPath('user.patronymic', null);
        $this->assertDatabaseHas('users', [
            'email' => 'anna@example.com', 'name' => 'де ла Крус Анна Мария',
            'first_name' => 'Анна Мария', 'last_name' => 'де ла Крус',
        ]);
    }

    public function test_legacy_registration_does_not_guess_parts(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Анна Мария де ла Крус',
            'email' => 'legacy@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone' => '+79991234567',
        ])->assertCreated()
            ->assertJsonPath('user.name', 'Анна Мария де ла Крус')
            ->assertJsonPath('user.first_name', null)
            ->assertJsonPath('user.last_name', null)
            ->assertJsonPath('user.patronymic', null);
    }

    public function test_registration_rejects_partial_or_conflicting_names(): void
    {
        $data = [
            'first_name' => 'Иван',
            'email' => 'incomplete@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone' => '+79991234567',
        ];
        $this->postJson('/api/v1/auth/register', $data)
            ->assertUnprocessable()->assertJsonValidationErrors('last_name');

        $this->postJson('/api/v1/auth/register', $data + [
            'last_name' => 'Петров', 'name' => 'Иван Петров',
        ])->assertUnprocessable()->assertJsonValidationErrors('name');
    }

    public function test_legacy_full_name_is_preserved_until_customer_converts_it(): void
    {
        $user = User::factory()->create(['name' => 'Иван Петров Сидоров']);
        $this->actingAs($user, 'web')->getJson('/api/v1/auth/me')
            ->assertOk()->assertJsonPath('user.name', 'Иван Петров Сидоров')
            ->assertJsonPath('user.first_name', null);

        $this->actingAs($user, 'web')->putJson('/api/v1/auth/profile', [
            'first_name' => 'Иван Петров',
            'last_name' => 'Сидоров',
            'patronymic' => null,
        ])->assertOk()
            ->assertJsonPath('user.name', 'Сидоров Иван Петров')
            ->assertJsonPath('user.first_name', 'Иван Петров')
            ->assertJsonPath('user.last_name', 'Сидоров');
    }

    public function test_existing_structured_profile_supports_partial_edit_and_clearing_patronymic(): void
    {
        $user = User::factory()->create([
            'first_name' => 'Иван', 'last_name' => 'Петров', 'patronymic' => 'Иванович',
        ]);
        $this->assertSame('Петров Иван Иванович', $user->name);
        $this->actingAs($user, 'web')->putJson('/api/v1/auth/profile', [
            'last_name' => 'Сидоров', 'patronymic' => null,
        ])->assertOk()
            ->assertJsonPath('user.name', 'Сидоров Иван')
            ->assertJsonPath('user.first_name', 'Иван')
            ->assertJsonPath('user.last_name', 'Сидоров')
            ->assertJsonPath('user.patronymic', null);
    }

    public function test_legacy_full_name_change_invalidates_structured_fields(): void
    {
        $user = User::factory()->create([
            'first_name' => 'Иван', 'last_name' => 'Петров', 'patronymic' => 'Иванович',
        ]);
        $this->actingAs($user, 'web')->putJson('/api/v1/auth/profile', [
            'name' => 'Анна Мария де ла Крус',
        ])->assertOk()
            ->assertJsonPath('user.name', 'Анна Мария де ла Крус')
            ->assertJsonPath('user.first_name', null)
            ->assertJsonPath('user.last_name', null)
            ->assertJsonPath('user.patronymic', null);
    }

    public function test_profile_rejects_conflicting_incomplete_and_overlong_structured_names(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'web')->putJson('/api/v1/auth/profile', [
            'name' => 'Иван Петров', 'first_name' => 'Иван', 'last_name' => 'Петров',
        ])->assertUnprocessable()->assertJsonValidationErrors('name');

        $this->actingAs($user, 'web')->putJson('/api/v1/auth/profile', [
            'first_name' => 'Иван',
        ])->assertUnprocessable()->assertJsonValidationErrors(['first_name', 'last_name']);

        $this->actingAs($user, 'web')->putJson('/api/v1/auth/profile', [
            'first_name' => str_repeat('И', 130), 'last_name' => str_repeat('П', 130),
        ])->assertUnprocessable()->assertJsonValidationErrors('last_name');
    }

    public function test_phone_only_update_does_not_touch_structured_name(): void
    {
        $user = User::factory()->create([
            'first_name' => 'Иван', 'last_name' => 'Петров', 'phone' => '+79991112233',
        ]);
        $this->actingAs($user, 'web')->putJson('/api/v1/auth/profile', [
            'phone' => '+79994445566',
        ])->assertOk()
            ->assertJsonPath('user.name', 'Петров Иван')
            ->assertJsonPath('user.first_name', 'Иван')
            ->assertJsonPath('user.last_name', 'Петров');
    }
}
