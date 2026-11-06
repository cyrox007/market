import type { WarehouseOption } from '../../../lib/api';
import { plural } from '../../../lib/plural';

export default function CheckoutDeliveryEstimate({ option }: { option: WarehouseOption }) {
  const min =
    option.delivery_days_min !== undefined ? option.delivery_days_min : option.delivery?.days_min;
  const max =
    option.delivery_days_max !== undefined ? option.delivery_days_max : option.delivery?.days_max;
  const canEstimate =
    !option.has_preorders &&
    !option.delivery_estimate_unavailable &&
    option.delivery_schedule?.status !== 'requires_confirmation' &&
    min != null &&
    max != null &&
    Number.isFinite(min) &&
    Number.isFinite(max) &&
    min >= 0 &&
    max >= min;
  return (
    <div className="space-y-2 text-14 text-ink-secondary">
      <p>
        {canEstimate
          ? `Ориентировочный срок: ${min === max ? min : `${min}–${max}`} ${plural(max!, ['день', 'дня', 'дней'])}`
          : 'Срок уточняется'}
      </p>
      <p className="text-12">
        {option.delivery_schedule?.notice ||
          'Точную дату и время доставки согласует менеджер после оформления заказа.'}
      </p>
    </div>
  );
}
