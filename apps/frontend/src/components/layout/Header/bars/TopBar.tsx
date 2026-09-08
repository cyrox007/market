import { Link } from 'react-router-dom';
import { ChevronDown, Clock, MapPin, Phone } from 'lucide-react';
import { cn } from '../../../../lib/cn';
import { DISABLED_LINK, HEADER_CONTAINER } from '../lib/container';

interface TopBarLink {
  to: string;
  label: string;
  /** Маршрута под раздел ещё нет: рисуем приглушённым и не кликаем */
  disabled?: boolean;
}

interface TopBarProps {
  city?: string;
  onCityClick?: () => void;
  links?: TopBarLink[];
  phone?: string;
  schedule?: string;
  className?: string;
}

const DEFAULT_LINKS: TopBarLink[] = [
  { to: '/stores', label: 'Магазины' },
  { to: '/delivery', label: 'Доставка и сборка' },
  // Маршрута /credit в router/config.tsx нет — ссылка увела бы на 404
  { to: '/credit', label: 'Кредит и рассрочка', disabled: true },
  { to: '/contacts', label: 'Контакты' },
];

/** Верхняя полоса шапки. Замеры и адаптив — market-docs/13-header.md */
export default function TopBar({
  city = 'Липецк',
  onCityClick,
  links = DEFAULT_LINKS,
  phone = '8 (800) 222-85-86',
  schedule = 'Ежедневно 9:00–21:00',
  className,
}: TopBarProps) {
  return (
    <div
      className={cn(
        'h-9 bg-surface-grey text-14 whitespace-nowrap text-ink max-md:text-12',
        className,
      )}
    >
      <div className={cn(HEADER_CONTAINER, 'flex h-full items-center justify-between')}>
        <div className="flex items-center gap-6">
          <button
            type="button"
            onClick={onCityClick}
            className="inline-flex items-center gap-1 outline-none hover:text-brand-green focus-visible:text-brand-green"
          >
            <MapPin className="size-4" aria-hidden="true" />
            {city}
            <ChevronDown className="size-4" aria-hidden="true" />
          </button>

          {/* Ниже 769 остаются только город и телефон */}
          {links.map(({ to, label, disabled }) =>
            disabled ? (
              <span key={to} aria-disabled="true" className={cn(DISABLED_LINK, 'max-sm:hidden')}>
                {label}
              </span>
            ) : (
              <Link
                key={to}
                to={to}
                className="outline-none hover:text-brand-green focus-visible:text-brand-green max-sm:hidden"
              >
                {label}
              </Link>
            ),
          )}
        </div>

        <div className="flex items-center gap-6">
          <a
            href={`tel:${phone.replace(/[^\d+]/g, '')}`}
            className="inline-flex items-center gap-2 outline-none hover:text-brand-green focus-visible:text-brand-green"
          >
            <Phone className="size-4" aria-hidden="true" />
            {phone}
          </a>
          {/* Режим работы пропадает раньше остального — уже ниже 1280 */}
          <span className="inline-flex items-center gap-2 text-ink-secondary max-xl:hidden">
            <Clock className="size-4" aria-hidden="true" />
            {schedule}
          </span>
        </div>
      </div>
    </div>
  );
}
