<?php

namespace App\Services\Shipping;

use App\Models\Shipping\AdditionalService;
use App\Models\Shipping\DeliveryHandlingType;
use App\Models\Shipping\ShippingLocation;
use Illuminate\Validation\ValidationException;

class LegacyWarehouseServices
{
    public function calculate(?ShippingLocation $tariffs, array $selection): array
    {
        $assemblyRequested = ! empty($selection['requires_assembly']) || ($tariffs?->getEffectiveRequiresAssembly() ?? false);
        $requested = ! empty($selection['delivery_handling_type_id']) || $assemblyRequested || ! empty($selection['additional_services']);
        if ($requested && ! $tariffs?->is_active) {
            throw ValidationException::withMessages(['services' => 'Для профиля склада не настроены тарифы дополнительных услуг.']);
        }
        $result = ['handling_cost' => 0.0, 'assembly_cost' => 0.0, 'additional_services_cost' => 0.0, 'additional_services' => [], 'assembly_days' => null, 'requires_assembly' => $assemblyRequested];
        if (! empty($selection['delivery_handling_type_id'])) {
            $type = DeliveryHandlingType::where('is_active', true)->find($selection['delivery_handling_type_id']);
            $provider = app(DeliveryHandlingProvider::class);
            $floor = $selection['delivery_floor'] ?? null;
            if ($type) {
                foreach ($tariffs->getAncestorsIds() as $locationId) {
                    $pivot = \Illuminate\Support\Facades\DB::table('shipping_location_delivery_handling')
                        ->where('shipping_location_id', $locationId)->where('delivery_handling_type_id', $type->id)->first();
                    if ($pivot) {
                        if (! $pivot->is_active) {
                            throw ValidationException::withMessages(['delivery_handling_type_id' => 'Подъём отключён для выбранной территории.']);
                        }
                        break;
                    }
                }
            }
            if (! $type || ! $provider->isHandlingTypeAvailable($tariffs, $type)
                || ($type->requires_floor && ($floor === null || $floor < 0)) || ($type->max_floor && $floor > $type->max_floor)) {
                throw ValidationException::withMessages(['delivery_handling_type_id' => 'Подъём недоступен: проверьте тип обработки и этаж.']);
            }
            $price = $provider->getHandlingPrice($tariffs, $type, $floor);
            if ($price === null || $price < 0) {
                throw ValidationException::withMessages(['delivery_handling_type_id' => 'Стоимость подъёма не настроена.']);
            }
            $result['handling_cost'] = (float) $price;
        }
        if ($assemblyRequested) {
            $price = $tariffs->getEffectiveAssemblyPrice();
            if ($price === null || $price < 0) {
                throw ValidationException::withMessages(['requires_assembly' => 'Стоимость сборки не настроена.']);
            }
            $result['assembly_cost'] = (float) $price;
            $result['assembly_days'] = $tariffs->getEffectiveAssemblyDays();
        }
        $seen = [];
        foreach ($selection['additional_services'] ?? [] as $item) {
            $id = (int) $item['id'];
            if (isset($seen[$id]) || isset($item['price'])) {
                throw ValidationException::withMessages(['additional_services' => 'Услуги не должны повторяться; цену рассчитывает сервер.']);
            }
            $seen[$id] = true;
            $service = AdditionalService::where('is_active', true)->find($id);
            if (! $service || $service->price_type !== 'fixed') {
                throw ValidationException::withMessages(['additional_services' => 'Для услуги нет доступного фиксированного тарифа. Услуги «от» и по согласованию уточняет менеджер.']);
            }
            $price = null;
            foreach ($tariffs->getAncestorsIds() as $locationId) {
                $pivot = \Illuminate\Support\Facades\DB::table('shipping_location_additional_service')->where('shipping_location_id', $locationId)->where('additional_service_id', $service->id)->first();
                if ($pivot) {
                    $price = $pivot->is_active ? ($pivot->price ?? $service->base_price) : null;
                    break;
                }
            }
            if ($price === null || $price < 0) {
                throw ValidationException::withMessages(['additional_services' => 'Стоимость услуги не настроена.']);
            }
            $result['additional_services'][] = ['id' => $service->id, 'name' => $service->name, 'price' => (float) $price, 'price_type' => 'fixed', 'icon' => $service->icon];
            $result['additional_services_cost'] += (float) $price;
        }

        return $result;
    }
}
