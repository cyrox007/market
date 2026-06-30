<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessRaiffeisenEcomCallbackJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Webhook от Райффайзен e-commerce API (pay.raif.ru).
 * Формат: event "PAYMENT", data: { order: { id }, status: { value }, amount }.
 * Подпись в заголовке X-Api-Signature-SHA256.
 */
class RaiffeisenEcomCallbackController extends Controller
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
        $payload['_signature'] = $request->header('X-Api-Signature-SHA256');

        ProcessRaiffeisenEcomCallbackJob::dispatch($payload);

        return response()->json(['status' => 'ok'], 200);
    }
}
