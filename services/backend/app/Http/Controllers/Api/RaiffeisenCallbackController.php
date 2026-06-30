<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessRaiffeisenCallbackJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Callback от Райффайзен: принимаем запрос, ставим обработку в очередь и сразу возвращаем 200.
 * Так банк не получает таймаут и не повторяет запрос; разбор и обновление платежа — в ProcessRaiffeisenCallbackJob.
 */
class RaiffeisenCallbackController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->all();
        if (empty($payload)) {
            $payload = json_decode((string) $request->getContent(), true) ?? [];
        }
        if (!is_array($payload)) {
            $payload = [];
        }
        $payload['_request_ip'] = $request->ip();
        $payload['_request_user_agent'] = $request->userAgent();

        ProcessRaiffeisenCallbackJob::dispatch($payload);

        return response()->json(['status' => 'ok'], 200);
    }
}
