<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order\OrderStatus;
use App\Services\Integration\OneCOrderStatusUpdater;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OneCOrderStatusController extends Controller
{
    public function update(Request $request, int $orderId, OneCOrderStatusUpdater $updater): JsonResponse
    {
        $data = $request->validate([
            'event_id' => ['required', 'uuid'],
            'expected_status' => ['required', Rule::enum(OrderStatus::class)],
            'status' => ['required', Rule::enum(OrderStatus::class)],
            'comment' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        $result = $updater->apply(
            $orderId, $data,
            (int) $request->attributes->get('onec_orders_access_token_id'),
            $request->ip()
        );

        return response()->json(['data' => $result]);
    }
}
