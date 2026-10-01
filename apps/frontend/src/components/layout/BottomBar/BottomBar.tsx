import { NavLink } from 'react-router-dom';
import { ArrowLeftRight, Heart, House, ShoppingCart, User } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import CounterBadge from '../Header/menu/CounterBadge';

interface BottomBarProps {
  compareCount?: number;
  wishlistCount?: number;
  cartCount?: number;
  isAuthenticated?: boolean;
}

interface TabItem {
  to: string;
  label: string;
  icon: LucideIcon;
  count?: number;
  /** Только точное совпадение — иначе «Главная» горит на любой странице */
  end?: boolean;
}

/** Нижнее меню ниже 550. Замеры — market-docs/27-bottom-bar.md */
export default function BottomBar({
  compareCount = 0,
  wishlistCount = 0,
  cartCount = 0,
  isAuthenticated = false,
}: BottomBarProps) {
  const tabs: TabItem[] = [
    { to: '/', label: 'Главная', icon: House, end: true },
    { to: '/compare', label: 'Сравнить', icon: ArrowLeftRight, count: compareCount },
    { to: '/cart', label: 'Корзина', icon: ShoppingCart, count: cartCount },
    { to: '/favorites', label: 'Избранное', icon: Heart, count: wishlistCount },
    { to: isAuthenticated ? '/account' : '/login', label: 'Профиль', icon: User },
  ];

  return (
    <nav
      aria-label="Нижнее меню"
      className="fixed inset-x-0 bottom-0 z-30 hidden border-t border-surface-border bg-surface px-2 pb-[calc(4px+env(safe-area-inset-bottom))] pt-1 max-vsm:flex"
    >
      {tabs.map(({ to, label, icon: Icon, count, end }) => (
        <NavLink
          key={label}
          to={to}
          end={end}
          aria-label={count && count > 0 ? `${label}, ${count}` : undefined}
          className={({ isActive }) =>
            `flex min-w-0 flex-1 flex-col items-center gap-1 py-2 text-12 font-medium outline-none transition-colors focus-visible:text-brand-green motion-reduce:transition-none ${
              isActive ? 'text-ink' : 'text-ink-secondary'
            }`
          }
        >
          <span className="relative inline-flex">
            <Icon className="size-6" strokeWidth={1.6} aria-hidden="true" />
            {count !== undefined && <CounterBadge count={count} />}
          </span>
          <span className="whitespace-nowrap">{label}</span>
        </NavLink>
      ))}
    </nav>
  );
}
