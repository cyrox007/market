import { Link, useSearchParams } from 'react-router-dom';
import { useAuth } from '../../hooks/useAuth';
import { usePageSeo } from '../../hooks/usePageSeo';
import { buildTitle } from '../../constants/seo';
import { useAccountOrders } from '../account/useAccountData';
import { AccountDataError } from '../account/AccountDataError';
import OrderSummaryRow from '../../components/orders/OrderSummaryRow';
import { orderGroup } from '../../components/orders/order-group';
import Button from '../../components/ui/primitives/Button';
import chevron from '../account/assets/address-chevron.svg';
import ordersIcon from '../account/assets/account-orders.svg';

const filters = [
  { value: 'all', label: 'Все' },
  { value: 'active', label: 'Активные' },
  { value: 'completed', label: 'Завершённые' },
] as const;

export default function Orders() {
  const { user } = useAuth();
  const orders = useAccountOrders(user?.id);
  const [params, setParams] = useSearchParams();
  const group = filters.find((filter) => filter.value === params.get('group'))?.value ?? 'all';
  const data = orders.data ?? [];
  const visible = group === 'all' ? data : data.filter((order) => orderGroup(order) === group);
  usePageSeo({ title: buildTitle('Мои заказы'), description: 'История ваших заказов в интернет-магазине Светофор-Мебель.', image: '/logo.png', open_graph_title: buildTitle('Мои заказы'), locale: 'ru_RU', robots: 'noindex, follow' });

  const selectGroup = (value: typeof group) => setParams((current) => {
    const next = new URLSearchParams(current);
    if (value === 'all') next.delete('group'); else next.set('group', value);
    return next;
  });

  return <div className="mx-auto w-full max-w-[1440px] px-[100px] pb-16 pt-6 text-ink max-md:px-6 max-vsm:px-4">
    <nav aria-label="Хлебные крошки" className="mb-6 flex flex-wrap items-center gap-2 text-14">
      <Link to="/" className="hover:text-brand-green">Главная</Link><img src={chevron} alt="" width={16} height={16} />
      <Link to="/account" className="hover:text-brand-green">Профиль</Link><img src={chevron} alt="" width={16} height={16} />
      <span aria-current="page" className="text-ink/50">Мои заказы</span>
    </nav>
    <h1 className="mb-6 font-display text-36 font-bold tracking-[-0.02em] max-vsm:text-28">Мои заказы</h1>
    {Boolean(orders.error) && <AccountDataError label="Не удалось загрузить заказы" onRetry={orders.mutate} />}
    {orders.data === undefined && orders.isLoading ? <div role="status" aria-label="Загрузка заказов" className="space-y-3">
      {Array.from({ length: 5 }, (_, i) => <div key={i} aria-hidden="true" className="flex min-h-[88px] items-center gap-5 px-4 py-4 max-vsm:flex-wrap">
        <span className="w-[184px] shrink-0 max-md:w-16"><span className="block size-14 animate-pulse rounded-btn bg-surface-grey motion-reduce:animate-none" /></span>
        <span className="flex min-w-0 flex-1 flex-col gap-1"><span className="h-5 w-44 max-w-full animate-pulse rounded bg-surface-grey motion-reduce:animate-none" /><span className="h-4 w-60 max-w-full animate-pulse rounded bg-surface-grey motion-reduce:animate-none" /><span className="h-4 w-80 max-w-full animate-pulse rounded bg-surface-grey motion-reduce:animate-none" /></span>
        <span className="flex flex-col items-end gap-1 max-vsm:hidden"><span className="h-6 w-24 animate-pulse rounded bg-surface-grey motion-reduce:animate-none" /><span className="h-4 w-16 animate-pulse rounded bg-surface-grey motion-reduce:animate-none" /></span>
      </div>)}
    </div> : orders.data !== undefined && (data.length ? <>
      <div role="group" aria-label="Фильтр заказов" className="mb-6 flex flex-wrap gap-2">
        {filters.map((filter) => <button key={filter.value} type="button" aria-pressed={group === filter.value} onClick={() => selectGroup(filter.value)} className={`inline-flex items-center gap-2 rounded-pill border px-4 py-2 text-14 font-medium outline-none focus-visible:ring-2 focus-visible:ring-brand-green ${group === filter.value ? 'border-ink' : 'border-surface-border hover:border-ink'}`}>
          {filter.label} <span>{filter.value === 'all' ? data.length : data.filter((order) => orderGroup(order) === filter.value).length}</span>
        </button>)}
      </div>
      {visible.length ? <div className="flex flex-col gap-3">{visible.map((order) => <OrderSummaryRow key={order.id} order={order} />)}</div> : <p className="rounded-popover bg-surface-grey p-8 text-ink-secondary">{group === 'completed' ? 'Завершённых заказов пока нет' : 'Активных заказов пока нет'}</p>}
    </> : <section className="flex flex-col items-center gap-4 rounded-popover bg-surface-grey px-6 py-16 text-center">
      <span className="flex size-16 items-center justify-center rounded-full bg-surface"><img src={ordersIcon} alt="" width={22} height={22} /></span>
      <h2 className="text-24 font-semibold max-vsm:text-20">У вас пока нет заказов</h2>
      <p className="max-w-[560px] text-14 text-ink-secondary">Здесь появятся ваши заказы с составом, статусом и адресом доставки.</p>
      <Button to="/catalog" shape="rounded">Перейти в каталог</Button>
    </section>)}
  </div>;
}
