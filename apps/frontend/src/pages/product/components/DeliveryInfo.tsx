import type { ReactNode } from 'react';
import deliveryIcon from '../../../assets/icons/delivery-fast.svg';
import pickupIcon from '../../../assets/icons/pickup-shop.svg';

interface Props {
  city: string | null;
  delivery: string | null;
  pickup: string | null;
}

/** «Доставка: г. … — срок» и «Самовывоз: …» (Figma 669:43425). Значки — из макета */
export default function DeliveryInfo({ city, delivery, pickup }: Props) {
  if (!delivery && !pickup) return null;
  return (
    <div className="flex flex-col gap-2 animate-fadeIn motion-reduce:animate-none">
      {delivery && (
        <Row
          icon={
            // В макете контур 21,87 × 18,99 внутри квадрата 24
            <span className="relative size-6 shrink-0">
              <img
                src={deliveryIcon}
                alt=""
                width={21.8686}
                height={18.9863}
                className="absolute left-[1.13px] top-[2.5px]"
              />
            </span>
          }
          label="Доставка:"
          city={city}
          value={delivery}
        />
      )}
      {pickup && (
        <Row
          icon={<img src={pickupIcon} alt="" width={24} height={24} className="shrink-0" />}
          label="Самовывоз:"
          city={city}
          value={pickup}
        />
      )}
    </div>
  );
}

function Row({
  icon,
  label,
  city,
  value,
}: {
  icon: ReactNode;
  label: string;
  city: string | null;
  value: string;
}) {
  return (
    <p className="flex items-center gap-2 text-14 font-medium text-ink">
      {icon}
      <span>{label}</span>{' '}
      <span>
        {city && <span className="text-ink-secondary">г. {city} — </span>}
        <span className="text-brand-green">{value}</span>
      </span>
    </p>
  );
}
