<?php

namespace App\Actions\Integration\Integration1C;

use App\Models\Order\Order;
use App\Support\Integration\OrderSyncEmailNormalizer;
use App\Models\Order\OrderItem;
use App\Models\Payment\PaymentMethod;
use App\Models\Product\Product;
use Illuminate\Support\Str;
use Vanilo\Payment\Models\PaymentStatusProxy;

class BuildOrderSyncPayloadAction
{
    public function __construct(
        private readonly OrderSyncEmailNormalizer $emailNormalizer,
    ) {
    }

    public function execute(Order $order): array
    {
        $order->loadMissing([
            'items.product.parentProduct',
            'address',
            'deliveryHandlingType',
        ]);

        return [
            'orderId' => (int) $order->id,
            'createdAt' => $this->formatCreatedAt($order),
            'fullName' => (string) ($order->contact_name ?? ''),
            'address' => $this->formatAddress($order),
            'phone' => (string) ($order->contact_phone ?? ''),
            'email' => $this->emailNormalizer->normalize($order->contact_email, (int) $order->id),
            'pickFromStore' => (bool) ($order->delivery_type === 'pickup'),
            'delivery' => (string) ($order->delivery_type === 'pickup' ? 'Самовывоз' : 'Курьером'),
            'deliveryCost' => round((float) ($order->delivery_cost ?? 0), 2),
            'lift' => $this->formatLiftDescription($order),
            'liftCost' => $this->resolveLiftCost($order),
            'payment' => $this->resolvePaymentLabel($order),
            'products' => $this->buildProducts($order),
            'status' => (string) $order->status,
        ];
    }

    private function formatCreatedAt(Order $order): ?string
    {
        if ($order->created_at === null) {
            return null;
        }

        return $order->created_at->clone()->utc()->format('Y-m-d\TH:i:s.v\Z');
    }

    /**
     * Текст услуги подъёма/разгрузки для 1С (тип обработки + этаж).
     */
    private function formatLiftDescription(Order $order): string
    {
        $type = $order->deliveryHandlingType;
        if ($type === null) {
            return '';
        }

        $name = trim((string) ($type->name ?? ''));
        $floor = $order->delivery_floor;

        if ($type->requires_floor && $floor !== null) {
            $suffix = 'на ' . $floor . ' этаж';
            if ($name !== '') {
                return $name . ', ' . $suffix;
            }

            return 'Подъём ' . $suffix;
        }

        return $name;
    }

    /**
     * Стоимость подъёма отдельно от доставки в нашей модели не хранится — она включена в delivery_cost / assembly_cost.
     * Передаём 0, если подъём не выделен; при необходимости позже можно разнести по правилам магазина.
     */
    private function resolveLiftCost(Order $order): float
    {
        if ($this->formatLiftDescription($order) === '') {
            return 0.0;
        }

        return 0.0;
    }

    /**
     * Способ оплаты — человекочитаемое имя из справочника payment_methods (как ожидает контракт 1С).
     */
    private function resolvePaymentLabel(Order $order): string
    {
        if (filled($order->payment_method)) {
            $name = PaymentMethod::query()
                ->where('code', $order->payment_method)
                ->value('name');
            if (filled($name)) {
                return (string) $name;
            }
        }

        return $this->resolvePaymentStatusLabel($order);
    }

    private function formatAddress(Order $order): string
    {
        if ($order->address === null) {
            return '';
        }

        $parts = array_filter([
            $order->address->city ? 'г. ' . $order->address->city : null,
            $order->address->street ? 'ул. ' . $order->address->street : null,
            $order->address->house ? 'д. ' . $order->address->house : null,
            $order->address->apartment ? 'кв. ' . $order->address->apartment : null,
        ]);

        return implode(', ', $parts);
    }

    private function resolvePaymentStatusLabel(Order $order): string
    {
        $payment = $order->payments()->orderByDesc('id')->first();
        if ($payment === null) {
            return 'Не оплачен';
        }

        if ($payment->getStatus()->equals(PaymentStatusProxy::PAID()) || (float) $payment->getAmountPaid() > 0.0) {
            return 'Оплачен';
        }

        return 'Не оплачен';
    }

    private function buildProducts(Order $order): array
    {
        $products = [];

        foreach ($order->items as $item) {
            $product = $item->product;
            if ($product === null) {
                continue;
            }

            $isVariant = method_exists($product, 'isVariant') && $product->isVariant();
            $parent = $isVariant ? $product->parentProduct : null;

            $productExternalId = (string) ($isVariant ? ($parent?->external_id ?? '') : ($product->external_id ?? ''));
            if ($productExternalId === '') {
                $productExternalId = (string) ($product->external_id ?? $product->id);
            }

            $modifications = [];
            if ($isVariant && !empty($product->external_id)) {
                $modifications[] = (string) $product->external_id;
            }

            [$catalogUnit, $paidUnit] = $this->resolveLinePrices($product, $item);

            $products[] = [
                'productId' => $this->normalizeExternalId($productExternalId),
                'modifications' => $modifications,
                'quantity' => (int) $item->quantity,
                'price' => round($catalogUnit, 2),
                'promoPrice' => round($paidUnit, 2),
            ];
        }

        return $products;
    }

    /**
     * @return array{0: float, 1: float} Каталожная цена за единицу и фактическая (акционная) за единицу.
     */
    private function resolveLinePrices(Product $product, OrderItem $item): array
    {
        $paidUnit = (float) $item->price;
        $original = $product->getOriginalPrice();
        if ($original !== null && $original > $paidUnit) {
            return [$original, $paidUnit];
        }

        return [$paidUnit, $paidUnit];
    }

    private function normalizeExternalId(string $externalId): string
    {
        return Str::of($externalId)->trim()->toString();
    }
}
