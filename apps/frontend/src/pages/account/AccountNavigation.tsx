import { Link, useSearchParams } from 'react-router-dom';
import type { ReactNode } from 'react';
import type { Order } from '../../lib/api';
import { plural } from '../../lib/plural';
import ordersIcon from './assets/account-orders.svg';
import favoritesIcon from './assets/account-favorites.svg';
import compareIcon from './assets/account-compare.svg';
import addressesIcon from './assets/account-addresses.svg';
import settingsIcon from './assets/account-settings.svg';
import logoutIcon from './assets/account-logout.svg';
import arrowIcon from './assets/account-arrow.svg';

interface ResourceState { isLoading: boolean; error?: unknown; hasData?: boolean }
interface Props {
  orders: ResourceState & { items: Pick<Order, 'status'>[] };
  addresses: ResourceState & { count: number };
  counters: ResourceState & { wishlistCount?: number; compareCount?: number };
  onLogout: () => void;
  isLoggingOut: boolean;
}

const activeStatuses = new Set(['new', 'manager_processing', 'assembling', 'awaiting_payment', 'accepted', 'assembled', 'shipped', 'in_transit']);
const tileClass = 'flex min-w-0 flex-col gap-3 rounded-popover border border-surface-border bg-surface p-4 text-left text-ink outline-none transition-colors hover:border-brand-yellow focus-visible:ring-2 focus-visible:ring-brand-green motion-reduce:transition-none max-vsm:flex-row max-vsm:items-center max-vsm:gap-3.5 max-vsm:py-5 max-vsm:pl-5';

function Count({ state, children }: { state: ResourceState; children: ReactNode }) {
  if (state.isLoading && !state.hasData) return <span role="status" aria-label="Загрузка количества" className="block h-4 w-24 max-w-full animate-pulse rounded bg-surface-grey motion-reduce:animate-none" />;
  return <span>{state.error && !state.hasData ? 'Не удалось загрузить' : children}</span>;
}

export default function AccountNavigation({ orders, addresses, counters, onLogout, isLoggingOut }: Props) {
  const [params] = useSearchParams();
  const sectionHref = (section: string) => {
    const next = new URLSearchParams(params);
    next.set('section', section);
    return `/account?${next}`;
  };
  const active = orders.items.filter((order) => activeStatuses.has(order.status)).length;
  const countProducts = (count: number | undefined) => count === undefined ? 'Не удалось загрузить' : `${count} ${plural(count, ['товар', 'товара', 'товаров'])}`;
  const tiles = [
    { title: 'Мои заказы', to: '/orders', icon: ordersIcon, state: orders,
      description: `${orders.items.length} ${plural(orders.items.length, ['заказ', 'заказа', 'заказов'])}, ${active} ${plural(active, ['активный', 'активных', 'активных'])}` },
    { title: 'Избранное', to: '/favorites', icon: favoritesIcon, state: counters, description: countProducts(counters.wishlistCount) },
    { title: 'Сравнение', to: '/compare', icon: compareIcon, state: counters, description: countProducts(counters.compareCount) },
    { title: 'Адреса доставки', to: sectionHref('addresses'), icon: addressesIcon, state: addresses,
      description: `${addresses.count} ${plural(addresses.count, ['адрес', 'адреса', 'адресов'])}` },
    { title: 'Настройки профиля', to: sectionHref('settings'), icon: settingsIcon, description: 'Личные данные, пароль' },
  ];

  return (
    <nav aria-label="Разделы кабинета" className="mb-6">
      <div className="grid grid-cols-5 gap-6 max-md:grid-cols-3 max-vsm:grid-cols-1 max-vsm:gap-3">
        {tiles.map((tile, index) => (
          <Link key={tile.to} to={tile.to} aria-label={tile.title} aria-describedby={`account-tile-${index}`} className={tileClass}>
            <span className="flex w-full flex-wrap items-center justify-between gap-2 max-vsm:w-auto max-vsm:shrink-0">
              <span className="flex size-11 shrink-0 items-center justify-center rounded-full bg-surface-grey"><img src={tile.icon} alt="" width={22} height={22} /></span>
              {tile.to === '/orders' && (orders.hasData || (!orders.isLoading && !orders.error)) && active > 0 && (
                <span aria-hidden="true" className="rounded-pill bg-brand-yellow px-2 py-0.5 text-12 font-medium max-vsm:hidden">{active} в работе</span>
              )}
            </span>
            <span className="flex min-w-0 flex-1 flex-col gap-1">
              <span className="text-16 font-semibold [overflow-wrap:anywhere]">
                {tile.icon === settingsIcon ? <><span className="max-vsm:hidden">Настройки</span><span className="vsm:hidden">Настройки профиля</span></> : tile.title}
              </span>
              <span id={`account-tile-${index}`} className="text-14 text-ink-secondary [overflow-wrap:anywhere]">
                {tile.state ? <Count state={tile.state}>{tile.description}</Count> : <><span className="max-vsm:hidden">Личные данные, пароль</span><span className="vsm:hidden">Данные, пароль, уведомления</span></>}
              </span>
            </span>
            <img src={arrowIcon} alt="" width={20} height={20} className="shrink-0 vsm:hidden" />
          </Link>
        ))}
      </div>
      <div className="mt-3 flex flex-col gap-3 vsm:mt-4 vsm:flex-row vsm:items-center vsm:justify-between">
        <button type="button" aria-label="Выйти" disabled={isLoggingOut} onClick={onLogout}
          className="flex w-full items-center gap-3.5 rounded-popover border border-surface-border bg-surface py-5 pl-5 pr-4 text-left text-ink outline-none hover:border-brand-yellow focus-visible:ring-2 focus-visible:ring-brand-green disabled:cursor-wait disabled:opacity-60 vsm:w-auto vsm:rounded-badge vsm:border-transparent vsm:p-1.5">
          <span className="flex size-11 shrink-0 items-center justify-center rounded-full bg-surface-grey vsm:hidden"><img src={logoutIcon} alt="" width={22} height={22} /></span>
          <span className="min-w-0 flex-1"><span className="block text-16 font-semibold">{isLoggingOut ? 'Выход…' : 'Выйти'}</span><span className="mt-1 block text-14 text-ink-secondary vsm:hidden">Завершить сеанс</span></span>
          <img src={arrowIcon} alt="" width={20} height={20} className="shrink-0 vsm:hidden" />
        </button>
        <Link to={sectionHref('bonuses')} className="text-14 text-ink-secondary underline underline-offset-4 focus-visible:outline-brand-green vsm:order-first">Бонусы</Link>
      </div>
    </nav>
  );
}
