<?php
namespace App\Services\Order;

use App\Models\Order\Order;
use App\Models\Page\Store;
use App\Models\Settings\ContactSettings;
use App\Services\Inventory\LocalStoreResolver;
use App\Support\PhoneNumber;

class OrderManagerContact
{
    public function resolve(Order $order): array
    {
        $store = $this->store($order);
        $global = ContactSettings::query()->find(1);
        $phone = $this->phone($store?->phone);
        $phoneSource = $phone ? 'regional_store' : null;
        if (! $phone) {
            $phone = collect($global?->phones ?? [])->map(fn ($v) => $this->phone(is_array($v) ? ($v['phone'] ?? null) : $v))->first(fn ($v) => $v !== null);
            $phoneSource = $phone ? 'global' : null;
        }
        $email = $this->email($store?->email);
        $emailSource = $email ? 'regional_store' : null;
        if (! $email) {
            $email = collect($global?->emails ?? [])->map(fn ($v) => $this->email(is_array($v) ? ($v['email'] ?? null) : $v))->first(fn ($v) => $v !== null);
            $emailSource = $email ? 'global' : null;
        }
        $actions = [];
        if ($phone) { $actions[] = ['type' => 'phone', 'label' => 'Позвонить менеджеру', 'url' => 'tel:'.$phone]; }
        if ($email) { $actions[] = ['type' => 'email', 'label' => 'Написать менеджеру', 'url' => 'mailto:'.$email.'?subject='.rawurlencode('Заказ '.$order->number)]; }
        return [
            'store_id' => $store?->id, 'store_name' => $store?->name,
            'phone' => $phone ? ['value' => $phone, 'source' => $phoneSource] : null,
            'email' => $email ? ['value' => $email, 'source' => $emailSource] : null,
            'hours' => filled($store?->hours) ? $store->hours : null,
            'working_hours' => (filled($store?->hours) || filled($store?->opening_hours)) ? ($store->opening_hours ?? []) : ($global?->working_hours ?? []),
            'timezone' => $store?->timezone ?? 'Europe/Moscow',
            'actions' => $actions, 'primary_action' => $actions[0] ?? null,
        ];
    }

    private function store(Order $order): ?Store
    {
        // Fulfillment was validated and sealed by checkout; never read the current browser region.
        if ($order->delivery_type === 'pickup' && filled($order->fulfillment_snapshot['store_id'] ?? null)) {
            $store = Store::active()->find($order->fulfillment_snapshot['store_id']);
            if ($store) { return $store; }
        }
        $directory = $order->fulfillment_snapshot['address_directory'] ?? [];
        if (! empty($directory['path'])) {
            $store = app(LocalStoreResolver::class)->resolve(['path' => $directory['path']]);
            if ($store) { return $store; }
        }
        $order->loadMissing('region');
        $ids = $order->region?->getAncestorsIds() ?? [];
        $stores = Store::active()->whereIn('shipping_location_id', $ids)->ordered()->orderBy('id')->get()->groupBy('shipping_location_id');
        foreach ($ids as $id) {
            if ($store = $stores->get($id)?->first()) { return $store; }
        }
        return null;
    }

    private function phone(mixed $value): ?string
    {
        if (! is_string($value) || blank($value)) { return null; }
        $phone = PhoneNumber::normalize($value);
        return PhoneNumber::isValidRu($phone) ? $phone : null;
    }

    private function email(mixed $value): ?string
    {
        if (! is_string($value)) { return null; }
        $email = strtolower(trim($value));
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }
}
