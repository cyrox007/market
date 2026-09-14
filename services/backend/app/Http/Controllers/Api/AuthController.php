<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * @OA\Tag(
 *     name="Auth",
 *     description="Аутентификация и управление пользователем"
 * )
 */
class AuthController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/v1/auth/register",
     *     operationId="register",
     *     summary="Регистрация нового пользователя",
     *     description="Создает нового пользователя и автоматически авторизует его через сессию",
     *     tags={"Auth"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "email", "password", "password_confirmation"},
     *             @OA\Property(property="name", type="string", example="Иван Иванов", description="Имя пользователя"),
     *             @OA\Property(property="email", type="string", format="email", example="user@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="password123", minLength=8),
     *             @OA\Property(property="password_confirmation", type="string", format="password", example="password123"),
     *             @OA\Property(property="phone", type="string", nullable=true, example="+7 (999) 123-45-67")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Пользователь успешно зарегистрирован",
     *         @OA\JsonContent(
     *             @OA\Property(property="user", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Иван Иванов"),
     *                 @OA\Property(property="email", type="string", example="user@example.com"),
     *                 @OA\Property(property="phone", type="string", nullable=true, example="+7 (999) 123-45-67")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Ошибка валидации",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'nullable|string|max:50',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'], // casts() => hashed
            'phone' => $validated['phone'] ?? null,
        ]);

        // Используем session-based аутентификацию для cookie-based auth
        Auth::login($user);

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
            ],
        ], 201);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/login",
     *     operationId="login",
     *     summary="Вход в систему",
     *     description="Авторизует пользователя по email и паролю, создает сессию",
     *     tags={"Auth"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email", "password"},
     *             @OA\Property(property="email", type="string", format="email", example="user@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="password123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Успешная авторизация",
     *         @OA\JsonContent(
     *             @OA\Property(property="user", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Иван Иванов"),
     *                 @OA\Property(property="email", type="string", example="user@example.com"),
     *                 @OA\Property(property="phone", type="string", nullable=true, example="+7 (999) 123-45-67")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Неверные учетные данные",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(property="errors", type="object",
     *                 @OA\Property(property="email", type="array", @OA\Items(type="string", example="Неверный email или пароль"))
     *             )
     *         )
     *     )
     * )
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        /** @var User|null $user */
        $user = User::where('email', $validated['email'])->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Неверный email или пароль'],
            ]);
        }

        // Используем session-based аутентификацию для cookie-based auth
        Auth::login($user);

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
            ],
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/logout",
     *     summary="Выход из системы",
     *     description="Завершает сессию пользователя",
     *     tags={"Auth"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Успешный выход",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="OK")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Не авторизован"
     *     )
     * )
     */
    public function logout(Request $request): JsonResponse
    {
        // При sanctum request guard не поддерживает logout().
        // Выполняем logout через web guard для session-based auth.
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        Auth::forgetGuards();
        $request->setUserResolver(static fn () => null);

        return response()->json([
            'message' => 'OK',
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/auth/me",
     *     summary="Получить информацию о текущем пользователе",
     *     description="Возвращает данные авторизованного пользователя",
     *     tags={"Auth"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Данные пользователя",
     *         @OA\JsonContent(
     *             @OA\Property(property="user", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Иван Иванов"),
     *                 @OA\Property(property="email", type="string", example="user@example.com"),
     *                 @OA\Property(property="phone", type="string", nullable=true, example="+7 (999) 123-45-67")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Гость (не авторизован)",
     *         @OA\JsonContent(
     *             @OA\Property(property="user", type="null", nullable=true, example=null)
     *         )
     *     )
     * )
     */
    public function me(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = Auth::guard('web')->user();

        if (!$user) {
            return response()->json([
                'user' => null,
            ]);
        }

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
            ],
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/auth/profile",
     *     summary="Обновить профиль пользователя",
     *     description="Обновляет имя и телефон пользователя",
     *     tags={"Auth"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="Иван Петров"),
     *             @OA\Property(property="phone", type="string", nullable=true, example="+7 (999) 123-45-67")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Профиль обновлен",
     *         @OA\JsonContent(
     *             @OA\Property(property="user", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Иван Петров"),
     *                 @OA\Property(property="email", type="string", example="user@example.com"),
     *                 @OA\Property(property="phone", type="string", nullable=true, example="+7 (999) 123-45-67")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Не авторизован"
     *     )
     * )
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|nullable|string|max:50',
        ]);

        /** @var User $user */
        $user = Auth::guard('web')->user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated',
            ], 401);
        }

        $user->fill($validated);
        $user->save();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
            ],
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/auth/password",
     *     summary="Изменить пароль",
     *     description="Изменяет пароль пользователя",
     *     tags={"Auth"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"current_password", "password", "password_confirmation"},
     *             @OA\Property(property="current_password", type="string", format="password", example="oldpassword123"),
     *             @OA\Property(property="password", type="string", format="password", example="newpassword123", minLength=8),
     *             @OA\Property(property="password_confirmation", type="string", format="password", example="newpassword123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Пароль успешно изменен",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Пароль успешно изменен")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Ошибка валидации или неверный текущий пароль"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Не авторизован"
     *     )
     * )
     */
    public function changePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        /** @var User $user */
        $user = Auth::guard('web')->user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated',
            ], 401);
        }

        if (!Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Текущий пароль неверен'],
            ]);
        }

        $user->password = $validated['password']; // casts() => hashed
        $user->save();

        return response()->json([
            'message' => 'Пароль успешно изменен',
        ]);
    }
    /**
     * @OA\Post(
     *     path="/api/v1/auth/forgot-password",
     *     summary="Отправка ссылки для восстановления пароля",
     *     description="Отправляет email со ссылкой для сброса пароля. Не раскрывает, существует ли email.",
     *     tags={"Auth"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email"},
     *             @OA\Property(property="email", type="string", format="email", example="user@example.com")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Ссылка для сброса пароля отправлена",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Если email существует, мы отправили ссылку для восстановления пароля")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Ошибка валидации",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="Неверный email")
     *         )
     *     )
     * )
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $status = Password::sendResetLink($request->only('email'));

        return response()->json([
            'status' => 'success',
            'message' => __('If your email exists, we have sent a password reset link.')
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/reset-password",
     *     summary="Сброс пароля по токену",
     *     description="Сбрасывает пароль по токену из письма восстановления",
     *     tags={"Auth"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"token","email","password","password_confirmation"},
     *             @OA\Property(property="token", type="string", example="token_from_email"),
     *             @OA\Property(property="email", type="string", format="email", example="user@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="newpassword123", minLength=8),
     *             @OA\Property(property="password_confirmation", type="string", format="password", example="newpassword123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Пароль успешно сброшен",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Пароль успешно сброшен")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Ошибка токена или валидации",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="Токен недействителен или пароль не соответствует требованиям")
     *         )
     *     )
     * )
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string',
            'email' => 'required|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->password = $password; // Хэшируется автоматически через cast
                $user->setRememberToken(Str::random(60));
                $user->save();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json([
                'status' => 'success',
                'message' => __('Пароль успешно сброшен')
            ]);
        }

        return response()->json([
            'status' => 'error',
            'message' => __('Токен недействителен или пароль не соответствует требованиям')
        ], 422);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/auth/notification-settings",
     *     summary="Получить настройки уведомлений",
     *     description="Возвращает текущие настройки уведомлений пользователя",
     *     tags={"Auth"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Настройки уведомлений",
     *         @OA\JsonContent(
     *             @OA\Property(property="settings", type="object",
     *                 @OA\Property(property="email_promotions", type="boolean", example=true),
     *                 @OA\Property(property="sms_order_notifications", type="boolean", example=true),
     *                 @OA\Property(property="push_notifications", type="boolean", example=false),
     *                 @OA\Property(property="new_product_notifications", type="boolean", example=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Не авторизован"
     *     )
     * )
     */
    public function getNotificationSettings(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::guard('web')->user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated',
            ], 401);
        }

        return response()->json([
            'settings' => [
                'email_promotions' => $user->email_promotions ?? true,
                'sms_order_notifications' => $user->sms_order_notifications ?? true,
                'push_notifications' => $user->push_notifications ?? false,
                'new_product_notifications' => $user->new_product_notifications ?? true,
            ],
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/auth/notification-settings",
     *     summary="Обновить настройки уведомлений",
     *     description="Обновляет настройки уведомлений пользователя",
     *     tags={"Auth"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="email_promotions", type="boolean", example=true),
     *             @OA\Property(property="sms_order_notifications", type="boolean", example=true),
     *             @OA\Property(property="push_notifications", type="boolean", example=false),
     *             @OA\Property(property="new_product_notifications", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Настройки обновлены",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Настройки уведомлений обновлены"),
     *             @OA\Property(property="settings", type="object",
     *                 @OA\Property(property="email_promotions", type="boolean", example=true),
     *                 @OA\Property(property="sms_order_notifications", type="boolean", example=true),
     *                 @OA\Property(property="push_notifications", type="boolean", example=false),
     *                 @OA\Property(property="new_product_notifications", type="boolean", example=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Не авторизован"
     *     )
     * )
     */
    public function updateNotificationSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email_promotions' => 'sometimes|boolean',
            'sms_order_notifications' => 'sometimes|boolean',
            'push_notifications' => 'sometimes|boolean',
            'new_product_notifications' => 'sometimes|boolean',
        ]);

        /** @var User $user */
        $user = Auth::guard('web')->user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated',
            ], 401);
        }

        $user->fill($validated);
        $user->save();

        return response()->json([
            'message' => 'Настройки уведомлений обновлены',
            'settings' => [
                'email_promotions' => $user->email_promotions,
                'sms_order_notifications' => $user->sms_order_notifications,
                'push_notifications' => $user->push_notifications,
                'new_product_notifications' => $user->new_product_notifications,
            ],
        ]);
    }
}
