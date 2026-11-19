<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\ProcessSberbankCallbackJob;
use App\Models\Order\Order;
use App\Services\Order\AutomaticOrderRefunds;
use App\Services\Payment\LocalSberSimulator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

final class LocalSberController extends Controller
{
    public function show(string $id, LocalSberSimulator $simulator): View
    {
        $bank = $simulator->find($id);
        return view('local-sber-payment', [
            'bank' => $bank,
            'action' => URL::temporarySignedRoute('local-sber.decide', now()->addHour(), ['id' => $id]),
            'back' => $this->orderUrl((int) $bank->order_id),
        ]);
    }

    public function decide(string $id, Request $request, LocalSberSimulator $simulator): RedirectResponse
    {
        $bank = $simulator->find($id);
        $data = $request->validate(['scenario' => ['required', 'in:success,decline,cancel,timeout,notify,refund-check']]);
        $scenario = $data['scenario'];
        if ($scenario === 'refund-check') {
            // Both invocations use the existing persisted refund intent and bank status reconciliation.
            $order = Order::findOrFail($bank->order_id);
            abort_unless($order->status === 'cancelled', 409, 'Сначала отмените заказ на сайте.');
            app(AutomaticOrderRefunds::class)->execute($order->id);
            app(AutomaticOrderRefunds::class)->execute($order->id);
        } else {
            if ($scenario !== 'notify') {
                $simulator->decide($id, $scenario);
            }
            // Normal job re-queries the simulator through the bank client; no shop status writes here.
            ProcessSberbankCallbackJob::dispatchSync(['mdOrder' => $id, 'orderNumber' => $bank->order_number]);
        }
        return redirect($this->orderUrl((int) $bank->order_id));
    }

    private function orderUrl(int $id): string
    {
        return rtrim((string) config('app.url'), '/').'/orders/'.$id;
    }
}
