<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessSberbankCallbackJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Callback от Сбербанка (ecom): mdOrder, orderNumber, operation, status.
 * Сразу 200 OK — обработка в ProcessSberbankCallbackJob.
 */
class SberbankCallbackController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->all();
        if ($payload === []) {
            $payload = json_decode((string) $request->getContent(), true) ?? [];
        }
        if (!is_array($payload)) {
            $payload = [];
        }
        $payload['_request_ip'] = $request->ip();
        $payload['_request_user_agent'] = $request->userAgent();

        ProcessSberbankCallbackJob::dispatch($payload);

        return response()->json(['status' => 'ok'], 200);
    }
}
