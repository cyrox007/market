import type { PickupOption } from '../../../lib/api';
import pickupNoticeIcon from '../../../assets/icons/checkout-pickup-notice.svg';
import { formatReady } from '../../product/lib/delivery';

export default function CheckoutPickupNotice({
  point,
}: {
  point: Pick<PickupOption, 'ready' | 'address' | 'hours'>;
}) {
  return (
    <div
      role="status"
      aria-label="Готовность самовывоза"
      className="flex items-start gap-3 overflow-hidden rounded-btn bg-brand-yellow/10 px-4 py-3.5 text-14 font-medium text-ink"
    >
      <img
        src={pickupNoticeIcon}
        alt=""
        aria-hidden="true"
        width={24}
        height={24}
        className="shrink-0"
      />
      <p className="min-w-0 break-words">
        Обратите внимание, товар можно забрать
        {point.ready ? ` — ${formatReady(point.ready)}` : ' после подтверждения готовности'}
        {point.address ? ` по адресу ${point.address}` : ''}.
        {point.hours && ` Часы работы: ${point.hours}`}
      </p>
    </div>
  );
}
