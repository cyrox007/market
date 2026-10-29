<?php

namespace App\Services\Shipping;

use App\Models\Shipping\AdditionalService;
use App\Models\Shipping\DeliveryHandlingType;
use App\Models\Shipping\WarehouseDeliveryProfile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class WarehouseServices
{
    public function options(WarehouseDeliveryProfile $profile): array
    {
        if ($profile->service_tariffs === null) {
            throw ValidationException::withMessages(['services' => 'Сохраните тарифы услуг в профиле склада. Старый расчёт по локации отключён.']);
        }
        $tariffs = $this->validateTariffs($profile->service_tariffs);
        $handling = DeliveryHandlingType::where('is_active', true)->whereIn('id', array_column($tariffs['handling'] ?? [], 'id'))->get()->keyBy('id');
        $additional = AdditionalService::where('is_active', true)->where('price_type', 'fixed')
            ->whereIn('id', array_column($tariffs['additional'] ?? [], 'id'))->get()->keyBy('id');

        return [
            'assembly' => ['available' => isset($tariffs['assembly_price']), 'required' => (bool) ($tariffs['requires_assembly'] ?? false),
                'price' => isset($tariffs['assembly_price']) ? (float) $tariffs['assembly_price'] : null,
                'days' => isset($tariffs['assembly_days']) ? (int) $tariffs['assembly_days'] : null],
            'handling' => collect($tariffs['handling'] ?? [])->filter(fn ($rate) => isset($handling[$rate['id']]))->map(fn ($rate) => [
                ...$rate, 'name' => $handling[$rate['id']]->name, 'code' => $handling[$rate['id']]->code,
                'requires_floor' => $handling[$rate['id']]->requires_floor, 'max_floor' => $handling[$rate['id']]->max_floor,
                'requires_elevator' => $handling[$rate['id']]->requires_elevator,
            ])->values()->all(),
            'additional' => collect($tariffs['additional'] ?? [])->filter(fn ($rate) => isset($additional[$rate['id']]))->map(fn ($rate) => [
                'id' => (int) $rate['id'], 'name' => $additional[$rate['id']]->name, 'price' => (float) $rate['price'], 'price_type' => 'fixed',
            ])->values()->all(),
        ];
    }

    /** Only services available at every source can be selected for the whole order. */
    public function combineOptions(array $sources): array
    {
        $allAssembly = collect($sources)->every(fn ($s) => $s['assembly']['available']);
        $result = ['assembly' => [
            'available' => $allAssembly,
            'required' => collect($sources)->contains(fn ($s) => $s['assembly']['required']),
            'price' => $allAssembly ? round(collect($sources)->sum(fn ($s) => $s['assembly']['price']), 2) : null,
            'days' => collect($sources)->max(fn ($s) => $s['assembly']['days']),
        ], 'handling' => [], 'additional' => []];
        foreach (['handling', 'additional'] as $kind) {
            foreach ($sources[0][$kind] ?? [] as $option) {
                $rates = collect($sources)->map(fn ($s) => collect($s[$kind])->firstWhere('id', $option['id']));
                if ($rates->contains(null)) { continue; }
                if ($kind === 'additional') {
                    $option['price'] = round($rates->sum('price'), 2);
                } else {
                    foreach (['base_price', 'elevator_price'] as $key) {
                        unset($option[$key]);
                        if ($rates->every(fn ($r) => isset($r[$key]))) { $option[$key] = round($rates->sum($key), 2); }
                    }
                    $floors = [];
                    foreach ($rates->first()['floor_prices'] ?? [] as $floor) {
                        $prices = $rates->map(fn ($r) => collect($r['floor_prices'] ?? [])->firstWhere('floor', $floor['floor']));
                        if (! $prices->contains(null)) { $floors[] = ['floor' => $floor['floor'], 'price' => round($prices->sum('price'), 2)]; }
                    }
                    $option['floor_prices'] = $floors;
                }
                $result[$kind][] = $option;
            }
        }
        return $result;
    }

    public function validateTariffs(array $tariffs): array
    {
        return Validator::make($tariffs, [
            'requires_assembly' => 'sometimes|boolean',
            'assembly_price' => 'nullable|numeric|min:0|max:999999999.99',
            'assembly_days' => 'nullable|integer|min:0|max:3650',
            'handling' => 'sometimes|array|max:100',
            'handling.*' => 'array:id,base_price,elevator_price,floor_prices',
            'handling.*.id' => 'required|integer|distinct|exists:delivery_handling_types,id',
            'handling.*.base_price' => 'nullable|numeric|min:0|max:999999999.99',
            'handling.*.elevator_price' => 'nullable|numeric|min:0|max:999999999.99',
            'handling.*.floor_prices' => 'sometimes|array|max:300',
            'handling.*.floor_prices.*' => 'array:floor,price',
            'handling.*.floor_prices.*.floor' => 'required|integer|min:0|max:300',
            'handling.*.floor_prices.*.price' => 'required|numeric|min:0|max:999999999.99',
            'additional' => 'sometimes|array|max:100',
            'additional.*' => 'array:id,price',
            'additional.*.id' => 'required|integer|distinct|exists:additional_services,id',
            'additional.*.price' => 'required|numeric|min:0|max:999999999.99',
        ])->after(function ($validator) use ($tariffs): void {
            if (! empty($tariffs['requires_assembly']) && ! isset($tariffs['assembly_price'])) {
                $validator->errors()->add('assembly_price', 'Укажите стоимость обязательной сборки.');
            }
            foreach ($tariffs['handling'] ?? [] as $index => $handling) {
                if (! is_array($handling) || ! is_array($handling['floor_prices'] ?? [])) {
                    continue;
                }
                $floors = array_map('intval', array_column($handling['floor_prices'] ?? [], 'floor'));
                if (count(array_unique($floors)) !== count($floors)) {
                    $validator->errors()->add("handling.$index.floor_prices", 'Этажи не должны повторяться.');
                }
            }
        })->validate();
    }

    public function calculate(WarehouseDeliveryProfile $profile, array $selection): array
    {
        if ($profile->service_tariffs === null) {
            throw ValidationException::withMessages(['services' => 'Сохраните тарифы услуг в профиле склада. Старый расчёт по локации отключён.']);
        }
        $tariffs = $this->validateTariffs($profile->service_tariffs);
        $assembly = ! empty($selection['requires_assembly']) || ! empty($tariffs['requires_assembly']);
        $result = ['handling_cost' => 0.0, 'assembly_cost' => 0.0, 'additional_services_cost' => 0.0,
            'additional_services' => [], 'assembly_days' => null, 'requires_assembly' => $assembly];
        if (! empty($selection['delivery_handling_type_id'])) {
            $type = DeliveryHandlingType::where('is_active', true)->find($selection['delivery_handling_type_id']);
            $rate = collect($tariffs['handling'] ?? [])->firstWhere('id', $selection['delivery_handling_type_id']);
            $floor = $selection['delivery_floor'] ?? null;
            if (! $type || ! $rate || ($type->requires_floor && $floor === null)
                || ($floor !== null && (filter_var($floor, FILTER_VALIDATE_INT) === false || $floor < 0))
                || ($type->max_floor !== null && $floor !== null && $floor > $type->max_floor)) {
                throw ValidationException::withMessages(['delivery_handling_type_id' => 'Подъём недоступен: проверьте тариф и этаж.']);
            }
            $price = $type->code === 'elevator' && isset($rate['elevator_price']) ? $rate['elevator_price']
                : (($floor !== null ? collect($rate['floor_prices'] ?? [])->firstWhere('floor', $floor)['price'] ?? null : null) ?? $rate['base_price'] ?? null);
            if ($price === null) {
                throw ValidationException::withMessages(['delivery_handling_type_id' => 'Стоимость подъёма не настроена в профиле склада.']);
            }
            $result['handling_cost'] = round((float) $price, 2);
        }
        if ($assembly) {
            if (! isset($tariffs['assembly_price'])) {
                throw ValidationException::withMessages(['requires_assembly' => 'Стоимость сборки не настроена в профиле склада.']);
            }
            $result['assembly_cost'] = round((float) $tariffs['assembly_price'], 2);
            $result['assembly_days'] = isset($tariffs['assembly_days']) ? (int) $tariffs['assembly_days'] : null;
        }
        $seen = [];
        foreach ($selection['additional_services'] ?? [] as $item) {
            $id = (int) ($item['id'] ?? 0);
            if (isset($seen[$id]) || array_key_exists('price', $item)) {
                throw ValidationException::withMessages(['additional_services' => 'Услуги не должны повторяться; цену рассчитывает сервер.']);
            }
            $seen[$id] = true;
            $service = AdditionalService::where('is_active', true)->find($id);
            $rate = collect($tariffs['additional'] ?? [])->firstWhere('id', $id);
            if (! $service || $service->price_type !== 'fixed' || ! $rate) {
                throw ValidationException::withMessages(['additional_services' => 'Для услуги нет фиксированного тарифа в профиле склада.']);
            }
            $price = round((float) $rate['price'], 2);
            $result['additional_services'][] = ['id' => $id, 'name' => $service->name, 'price' => $price, 'price_type' => 'fixed', 'icon' => $service->icon];
            $result['additional_services_cost'] = round($result['additional_services_cost'] + $price, 2);
        }

        return $result;
    }
}
