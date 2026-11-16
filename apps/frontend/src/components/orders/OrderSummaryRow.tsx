import { Link } from 'react-router-dom';
import { useState } from 'react';
import { ImageIcon } from 'lucide-react';
import type { Order } from '../../lib/api';
import { plural } from '../../lib/plural';
import OrderStatusBadge from './OrderStatusBadge';
import arrow from './assets/order-chevron.svg';

const dateFormat = new Intl.DateTimeFormat('ru-RU', { day: 'numeric', month: 'long', year: 'numeric', timeZone: 'Europe/Moscow' });
const priceFormat = new Intl.NumberFormat('ru-RU', { maximumFractionDigits: 2 });

function OrderThumbnail({ src, name }: { src?: string | null; name: string }) {
  const [failedUrl, setFailedUrl] = useState<string | null>(null);
  if (!src || src === failedUrl) {
    return <span role="img" aria-label={`Нет изображения: ${name}`} className="flex size-14 shrink-0 items-center justify-center rounded-btn bg-surface-grey text-ink-secondary"><ImageIcon aria-hidden="true" className="size-6" /></span>;
  }
  return <img src={src} alt={name} width={56} height={56} onError={() => setFailedUrl(src)} className="size-14 shrink-0 rounded-btn object-cover" />;
}

export default function OrderSummaryRow({ order }: { order: Order }) {
  const items = order.items ?? [];
  const quantity = items.reduce((sum, item) => sum + item.quantity, 0);
  const date = new Date(order.created_at);
  const dateLabel = Number.isFinite(date.getTime()) ? `от ${dateFormat.format(date).replace(/ г\.$/, '')}` : 'Дата уточняется';
  const nameOf = (item: (typeof items)[number]) => item.product?.name || `Товар #${item.product_id}`;

  return (
    <Link to={`/orders/${order.id}`} aria-label={`Открыть заказ № ${order.number || order.id}`}
      className="flex h-[88px] min-w-0 items-center gap-5 rounded-btn bg-surface py-4 pl-4 pr-5 text-ink outline-none focus-visible:ring-2 focus-visible:ring-brand-green max-md:h-auto max-vsm:flex-col max-vsm:items-start">
      <span className="flex w-[184px] shrink-0 flex-wrap gap-2 max-md:w-[min(184px,30%)] max-vsm:w-full">
        {items.slice(0, 2).map((item) => <OrderThumbnail key={item.id} src={item.product?.image || item.product?.thumbnail} name={nameOf(item)} />)}
        {items.length > 2 && <span aria-label={`Ещё ${items.length - 2} ${plural(items.length - 2, ['позиция', 'позиции', 'позиций'])}`} className="flex size-14 shrink-0 items-center justify-center rounded-btn bg-surface-grey text-14 font-medium">+{items.length - 2}</span>}
      </span>
      <span className="flex min-w-0 flex-1 flex-col gap-1.5 max-vsm:w-full">
        <span className="flex min-w-0 items-center gap-x-3 gap-y-1.5 max-vsm:flex-wrap max-vsm:gap-x-2">
          <span className="min-w-0 max-w-full truncate text-16 font-semibold max-md:flex-1 max-vsm:flex-none max-vsm:whitespace-normal max-vsm:[overflow-wrap:anywhere]">Заказ № {order.number || order.id}</span>
          <OrderStatusBadge status={order.status} appearance="account" fallbackLabel={order.status_label} />
        </span>
        <span className="text-14 text-ink-secondary [overflow-wrap:anywhere]">{dateLabel}{order.items && `, ${quantity} ${plural(quantity, ['товар', 'товара', 'товаров'])}`}</span>
        <span className="truncate text-14 max-vsm:line-clamp-2 max-vsm:whitespace-normal">{items.length ? items.map(nameOf).join(', ') : 'Состав заказа уточняется'}</span>
      </span>
      <span className="flex min-w-0 max-w-[40%] shrink-0 flex-col gap-1 max-vsm:w-full max-vsm:max-w-full max-vsm:flex-row max-vsm:items-center max-vsm:justify-between max-vsm:gap-3">
        <span className="text-20 font-semibold [overflow-wrap:anywhere]">{Number.isFinite(order.total) ? `${priceFormat.format(order.total)} ₽` : 'Сумма уточняется'}</span>
        <span className="inline-flex shrink-0 items-center gap-1 text-14 font-medium">Подробнее<img src={arrow} alt="" width={16} height={16} /></span>
      </span>
    </Link>
  );
}
