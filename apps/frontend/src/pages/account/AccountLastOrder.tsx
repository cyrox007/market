import { Link } from 'react-router-dom';
import type { Order } from '../../lib/api';
import OrderSummaryRow from '../../components/orders/OrderSummaryRow';
import Button from '../../components/ui/primitives/Button';
import { AccountDataError } from './AccountDataError';
import arrow from '../../components/orders/assets/order-chevron.svg';

interface Props { orders: Order[]; isLoading: boolean; error?: unknown; onRetry: () => unknown }

export default function AccountLastOrder({ orders, isLoading, error, onRetry }: Props) {
  return (
    <section aria-label="Последний заказ" className="mb-6 flex min-w-0 flex-col gap-4 rounded-popover bg-surface-grey p-7 text-ink max-vsm:px-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <h2 className="text-20 font-semibold">Последний заказ</h2>
        <Link to="/orders" className="inline-flex shrink-0 items-center gap-1 text-14 font-medium outline-none focus-visible:ring-2 focus-visible:ring-brand-green">Все заказы<img src={arrow} alt="" width={16} height={16} /></Link>
      </div>
      {Boolean(error) && <AccountDataError label="Не удалось загрузить последний заказ" onRetry={onRetry} />}
      {orders[0] ? <OrderSummaryRow order={orders[0]} /> : isLoading ? (
        <div role="status" aria-label="Загрузка последнего заказа" className="flex min-h-[88px] items-center gap-5 rounded-btn bg-surface p-4 max-vsm:flex-col max-vsm:items-start">
          <span aria-hidden="true" className="size-14 shrink-0 animate-pulse rounded-btn bg-surface-grey motion-reduce:animate-none" />
          <span aria-hidden="true" className="flex w-full min-w-0 flex-col gap-2">
            <span className="h-4 w-1/3 animate-pulse rounded bg-surface-grey motion-reduce:animate-none" />
            <span className="h-3 w-1/2 animate-pulse rounded bg-surface-grey motion-reduce:animate-none" />
            <span className="h-3 w-2/3 animate-pulse rounded bg-surface-grey motion-reduce:animate-none" />
          </span>
        </div>
      ) : !error && (
        <div className="flex flex-col items-start gap-4">
          <p className="text-16 text-ink-secondary">У вас пока нет заказов</p>
          <Button to="/catalog" shape="rounded">Перейти в каталог</Button>
        </div>
      )}
    </section>
  );
}
