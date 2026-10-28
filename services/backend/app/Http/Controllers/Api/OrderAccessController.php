<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\OrderResource;
use App\Models\Order\Order;
use App\Models\Order\OrderStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderAccessController
{
    private function order(Request $request, int $id): Order
    {
        $order = Order::findOrFail($id);
        $user = auth('sanctum')->user();
        $owner = $user && $order->user_id && (int) $order->user_id === (int) $user->id;
        $token = $request->header('X-Order-Token', '');
        $guest = app(\App\Services\Order\GuestOrderAccess::class)->valid($order, $token);
        abort_unless($owner || $guest, 403);
        return $order;
    }

    public function show(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        $order = $this->order($request, $id);
        return response()->json(['order' => new OrderResource($order->load(['items.product', 'statusHistory', 'additionalServices', 'address']))])
            ->header('Cache-Control', 'no-store, private');
    }

    public function cancel(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        $order = $this->order($request, $id);
        $data = $request->validate(['reason' => 'nullable|string|max:1000']);
        DB::transaction(function () use ($order, $data): void {
            $locked = Order::lockForUpdate()->findOrFail($order->id);
            if (! $locked->canBeCancelled()) {
                throw \Illuminate\Validation\ValidationException::withMessages(['order' => 'Срок прямой отмены истёк. Создайте обращение в поддержку.']);
            }
            $locked->changeStatus(OrderStatus::CANCELLED, $data['reason'] ?? 'Отменён покупателем.');
        });
        return response()->json(['order' => new OrderResource($order->fresh())])->header('Cache-Control', 'no-store, private');
    }

    public function requestCancellation(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        $order = $this->order($request, $id);
        $data = $request->validate(['reason' => 'required|string|min:3|max:1000']);
        DB::transaction(function () use ($order, $data): void {
            $locked = Order::lockForUpdate()->findOrFail($order->id);
            abort_if(! $locked->cooling_until || now()->lt($locked->cooling_until)
                || in_array($locked->status, ['cancelled', 'delivered'], true), 422);
            DB::table('order_cancellation_requests')->insertOrIgnore(['order_id' => $order->id, 'reason' => $data['reason'],
                'status' => 'new', 'created_at' => now(), 'updated_at' => now()]);
        });
        return response()->json(['request' => DB::table('order_cancellation_requests')->where('order_id', $order->id)->first()], 202)
            ->header('Cache-Control', 'no-store, private');
    }
}
