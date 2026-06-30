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

        $email = $request->input('email');

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
}
