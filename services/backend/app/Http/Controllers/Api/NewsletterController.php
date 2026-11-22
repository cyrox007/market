<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Newsletter\NewsletterSubscriber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class NewsletterController extends Controller
{
    public function subscribe(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Ошибка валидации',
                'errors' => $validator->errors(),
            ], 422);
        }

        $email = strtolower(trim($request->input('email')));
        $user = \App\Models\User::where('email', $email)->first();
        if ($user && $request->user('web')?->id !== $user->id) {
            return response()->json(['message' => 'Управляйте подпиской в личном кабинете.', 'errors' => ['email' => ['Войдите в аккаунт для изменения подписки.']]], 422);
        }
        if ($user) {
            $user->update(['email_promotions' => true]);
        }

        // Проверяем, существует ли уже подписчик
        $subscriber = NewsletterSubscriber::where('email', $email)->first();

        if ($subscriber) {
            if ($subscriber->is_active) {
                return response()->json([
                    'message' => 'Вы уже подписаны на рассылку',
                ], 200);
            } else {
                // Активируем подписку, если она была деактивирована
                $subscriber->is_active = true;
                $subscriber->save();

                return response()->json([
                    'message' => 'Подписка успешно восстановлена',
                ], 200);
            }
        }

        // Создаем нового подписчика
        NewsletterSubscriber::create([
            'email' => $email,
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'Вы успешно подписались на рассылку',
        ], 201);
    }

    public function unsubscribeForm(Request $request): \Illuminate\Contracts\View\View
    {
        return view('newsletter.unsubscribe', ['action' => $request->fullUrl()]);
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        $email = strtolower(trim((string) $request->query('email')));
        \Illuminate\Support\Facades\DB::transaction(function () use ($email): void {
            NewsletterSubscriber::where('email', $email)->update(['is_active' => false]);
            \App\Models\User::where('email', $email)->update(['email_promotions' => false]);
        });
        return response()->json(['message' => 'Вы отписались от акций и скидок.']);
    }
}
