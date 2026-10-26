<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order\Order;
use App\Services\Order\OperatorOrder;
use Illuminate\Http\Request;

class OrderPrintController extends Controller
{
    public function __invoke(Request $request, int $orderId): \Illuminate\View\View
    {
        $record = Order::with(['items.product', 'payments', 'checkoutPaymentMethod', 'statusHistory.user'])->findOrFail($orderId);
        abort_unless($request->user()?->can('view', $record), 403);
        return view('admin.orders.print', ['order' => $record, 'operator' => app(OperatorOrder::class)]);
    }
}
