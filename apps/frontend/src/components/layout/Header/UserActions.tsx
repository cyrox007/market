import { Link } from 'react-router-dom';
import { ArrowLeftRight, Heart, ShoppingCart, User } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import CounterBadge from './CounterBadge';
import { cn } from '../../../lib/cn';

interface UserActionsProps {
  compareCount?: number;
  wishlistCount?: number;
  cartCount?: number;
  /** Вошедшему пользователю подпись меняется на «Кабинет» */
  isAuthenticated?: boolean;
  className?: string;
}

interface ActionItem {
  to: string;
  label: string;
  icon: LucideIcon;
  count?: number;
}

/**
 * Правая группа шапки: Сравнить, Избранное, Корзина, Войти.
 *
 * Замерено с макета 07.09.2026: группа целиком **298 × 43**, промежуток между
 * элементами **20**, иконки 24.
 *
 * Размер подписи выведен из ширины, а не замерен напрямую: при 14 четыре слова
 * с промежутками дают около 298, при 12 — заметно меньше. Проверяется в песочнице
 * замером ширины группы, там же стоит вариант с 12 для сравнения.
 *
 * ⚠️ Не замерено: промежуток между иконкой и подписью (стоит 2, выводится из
 * высоты 43 = 24 + 2 + 17).
 *
 * Адаптив: ниже 980 подписи пропадают, остаются иконки со счётчиками. Ниже 550
 * группа исчезает целиком — это делает MainBar, а не сам компонент.
 */
export default function UserActions({
  compareCount = 0,
  wishlistCount = 0,
  cartCount = 0,
  isAuthenticated = false,
  className,
}: UserActionsProps) {
  const items: ActionItem[] = [
    { to: '/compare', label: 'Сравнить', icon: ArrowLeftRight, count: compareCount },
    { to: '/favorites', label: 'Избранное', icon: Heart, count: wishlistCount },
    { to: '/cart', label: 'Корзина', icon: ShoppingCart, count: cartCount },
    {
      to: isAuthenticated ? '/account' : '/login',
      label: isAuthenticated ? 'Кабинет' : 'Войти',
      icon: User,
    },
  ];

  return (
    <nav className={cn('flex items-start gap-5', className)} aria-label="Личные разделы">
      {items.map(({ to, label, icon: Icon, count }) => (
        <Link
          key={to}
          to={to}
          className="group inline-flex flex-col items-center gap-0.5 text-14 text-ink outline-none hover:text-brand-green focus-visible:text-brand-green"
        >
          <span className="relative inline-flex">
            <Icon className="size-6" aria-hidden="true" />
            {count !== undefined && <CounterBadge count={count} />}
          </span>
          {/* Ниже 980 в макете остаются одни иконки со счётчиками */}
          <span className="whitespace-nowrap max-md:hidden">{label}</span>
        </Link>
      ))}
    </nav>
  );
}
