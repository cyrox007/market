<?php

namespace App\Http\Controllers\Admin;

use App\Models\Order\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class OrderPrintController
{
    /**
     * Показать печатную версию заказа
     */
    public function __invoke(Request $request, $orderId): View
    {
        $orderId = (int) $orderId;
        
        // Получаем заказ для проверки прав доступа (но не используем его для данных)
        $orderForCheck = Order::find($orderId);
        
        if (!$orderForCheck) {
            abort(404, 'Заказ не найден');
        }
        
        // Проверяем права доступа (только менеджеры и выше)
        if (!$request->user() || !$request->user()->can('view', $orderForCheck)) {
            abort(403, 'У вас нет прав для просмотра этого заказа');
        }

        // Получаем все данные заказа напрямую из БД, минуя модель, чтобы избежать каста Vanilo enum
        $orderData = DB::table('orders')->where('id', $orderId)->first();
        
        if (!$orderData) {
            abort(404, 'Заказ не найден');
        }

        // Получаем связанные данные
        $user = $orderData->user_id ? \App\Models\User::find($orderData->user_id) : null;
        $address = $orderData->address_id ? \App\Models\Address\Address::find($orderData->address_id) : null;
        $shippingLocation = $orderData->shipping_location_id ? \App\Models\Shipping\ShippingLocation::find($orderData->shipping_location_id) : null;
        $shippingMethod = $orderData->shipping_method_id ? \Vanilo\Shipment\Models\ShippingMethod::with('carrier')->find($orderData->shipping_method_id) : null;
        $deliveryHandlingType = $orderData->delivery_handling_type_id ? \App\Models\Shipping\DeliveryHandlingType::find($orderData->delivery_handling_type_id) : null;
        
        // Получаем товары заказа
        $items = DB::table('order_items')
            ->where('order_id', $orderId)
            ->get()
            ->map(function ($item) {
                $product = $item->product_id ? \App\Models\Product\Product::find($item->product_id) : null;
                return [
                    'product' => $product,
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'price' => (float) $item->price,
                    'total' => (float) $item->total,
                ];
            })
            ->values();
        
        // Получаем дополнительные услуги
        $additionalServices = DB::table('order_additional_services')
            ->where('order_id', $orderId)
            ->join('additional_services', 'order_additional_services.additional_service_id', '=', 'additional_services.id')
            ->select(
                'order_additional_services.service_name',
                'order_additional_services.price',
                'order_additional_services.price_type',
                'additional_services.name',
                'additional_services.icon'
            )
            ->get();
        
        // Рассчитываем сумму дополнительных услуг (исключая услуги с типом 'custom')
        $additionalServicesTotal = $additionalServices->sum(function ($service) {
            $priceType = $service->price_type ?? 'fixed';
            if ($priceType === 'custom') {
                return 0;
            }
            return (float) ($service->price ?? 0);
        });
        
        // Получаем историю статусов напрямую из БД, минуя модель
        $statusHistoryData = DB::table('order_status_history')
            ->where('order_id', $orderId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($history) {
                $user = $history->user_id ? \App\Models\User::find($history->user_id) : null;
                return [
                    'status' => (string) $history->status,
                    'created_at' => \Carbon\Carbon::parse($history->created_at),
                    'user' => $user,
                    'comment' => $history->comment ?? null,
                ];
            });

        return view('admin.orders.print', [
            'orderData' => $orderData,
            'orderStatus' => (string) $orderData->status,
            'user' => $user,
            'address' => $address,
            'shippingLocation' => $shippingLocation,
            'shippingMethod' => $shippingMethod,
            'deliveryHandlingType' => $deliveryHandlingType,
            'items' => $items,
            'additionalServices' => $additionalServices,
            'additionalServicesTotal' => $additionalServicesTotal,
            'statusHistoryData' => $statusHistoryData,
        ]);
    }
}
