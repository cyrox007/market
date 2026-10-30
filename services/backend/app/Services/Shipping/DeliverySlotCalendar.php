<?php

namespace App\Services\Shipping;

use App\Models\Order\Order;
use App\Models\Shipping\WarehouseDeliveryProfile;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Actual capacity-backed delivery windows for a selected warehouse profile.
 * FulfillmentDates::range() is an ETA, NOT a booking calendar.
 */
class DeliverySlotCalendar
{
    public const TIMEZONE = 'Europe/Moscow';
    public const HORIZON_DAYS = 45;

    public function forOption(?array $option): array
    {
        $base = ['status' => 'unconfigured', 'timezone' => self::TIMEZONE,
            'selection_required' => false, 'dates' => []];
        if (! $option || empty($option['profile_id'])) {
            return $base;
        }

        $profile = WarehouseDeliveryProfile::find((int) $option['profile_id']);
        if (! $profile || ! $profile->is_active || ! $profile->delivery_weekly_slots) {
            return $base;
        }

        $base['selection_required'] = true;
        if (! empty($option['has_preorders']) || empty($option['delivery']['date_max'])) {
            return [...$base, 'status' => 'requires_confirmation', 'selection_required' => false];
        }

        $today = CarbonImmutable::today(self::TIMEZONE);
        // Use the upper bound of the fulfillment ETA: don't promise delivery
        // before the worst-case stock preparation and transport time.
        $earliest = CarbonImmutable::parse($option['delivery']['date_max'], self::TIMEZONE)
            ->startOfDay();
        if ($earliest->lt($today->addDay())) {
            $earliest = $today->addDay();
        }
        $end = $today->addDays(self::HORIZON_DAYS);
        if ($earliest->gt($end)) {
            return [...$base, 'status' => 'requires_confirmation', 'selection_required' => false];
        }

        $booked = DB::table('delivery_slot_reservations as r')
            ->join('orders as o', 'o.id', '=', 'r.order_id')
            ->where('r.warehouse_delivery_profile_id', $profile->id)
            ->whereBetween('r.delivery_date', [$earliest->toDateString(), $end->toDateString()])
            ->where('o.status', '!=', 'cancelled')
            ->select('r.delivery_date', 'r.delivery_time')
            ->selectRaw('COUNT(*) AS used')
            ->groupBy('r.delivery_date', 'r.delivery_time')
            ->get()
            ->keyBy(fn ($row): string => $row->delivery_date . '|' . $row->delivery_time);

        $dates = [];
        $hasOpen = false;
        for ($day = $earliest; $day->lte($end); $day = $day->addDay()) {
            $slots = [];
            foreach ($profile->delivery_weekly_slots as $rule) {
                if ((int) $rule['weekday'] !== $day->dayOfWeekIso) {
                    continue;
                }
                $time = $rule['start'] . '-' . $rule['end'];
                $occupied = (int) ($booked[$day->toDateString() . '|' . $time]->used ?? 0);
                $left = max(0, (int) $rule['capacity'] - $occupied);
                $slots[] = ['time' => $time, 'start' => $rule['start'], 'end' => $rule['end'],
                    'available' => $left > 0, 'remaining' => $left];
                $hasOpen = $hasOpen || $left > 0;
            }
            if ($slots !== []) {
                $dates[] = ['date' => $day->toDateString(), 'available' => collect($slots)->contains('available', true),
                    'slots' => $slots];
            }
        }

        return [...$base, 'status' => $hasOpen ? 'available' : 'fully_booked', 'dates' => $dates];
    }

    /**
     * Run inside the order transaction. Locking the profile serializes slot
     * reservations and ensures two checkouts cannot book the final capacity.
     */
    public function reserve(Order $order, array $option, string $date, string $time): void
    {
        $profileId = (int) ($option['profile_id'] ?? 0);
        if ($profileId === 0 || ! WarehouseDeliveryProfile::whereKey($profileId)
            ->lockForUpdate()->first()) {
            throw ValidationException::withMessages(['delivery_date' => 'Выбранный профиль доставки недоступен.']);
        }

        $calendar = $this->forOption($option);
        $day = collect($calendar['dates'])->firstWhere('date', $date);
        $slot = collect($day['slots'] ?? [])->firstWhere('time', $time);
        if ($calendar['status'] !== 'available' || ! ($slot['available'] ?? false)) {
            throw ValidationException::withMessages(['delivery_time' => 'Интервал доставки занят или более недоступен. Обновите календарь.']);
        }

        DB::table('delivery_slot_reservations')->insert([
            'order_id' => $order->id,
            'warehouse_delivery_profile_id' => $profileId,
            'delivery_date' => $date,
            'delivery_time' => $time,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
