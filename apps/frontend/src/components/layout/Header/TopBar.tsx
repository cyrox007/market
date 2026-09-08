import { Link } from 'react-router-dom';
import { ChevronDown, Clock, MapPin, Phone } from 'lucide-react';
import { cn } from '../../../lib/cn';
import { HEADER_CONTAINER } from './container';

interface TopBarLink {
  to: string;
  label: string;
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
  { to: '/credit', label: 'Кредит и рассрочка' },
  { to: '/contacts', label: 'Контакты' },
];

/**
 * Верхняя полоса шапки: выбор города, служебные ссылки, телефон и режим работы.
 *
 * Замерено с макета 07.09.2026: высота полосы **36**, промежуток **24**.
 *
 * ⚠️ Не замерено: размер шрифта (стоит 14), размер иконок (16), цвет подложки.
 * На скриншоте полоса чуть серее белого — взят `surface-grey`.
 *
 * Адаптив по макету:
 *   ниже 1280  пропадает режим работы — раньше всего остального
 *   ниже 980   размер шрифта становится 12
 *   ниже 769   пропадают служебные ссылки, остаются город и телефон
 *
 * Порог 769 — это `sm`, он был в конфиге. В макете нарисовано только состояние
 * ниже 550; владелец попросил включать его раньше, примерно с 740.
 *
 * `whitespace-nowrap` на всей полосе: между 1440 и 980 макета нет, а без запрета
 * переноса ссылки ломаются по словам уже на 1152.
 */
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
          {links.map(({ to, label }) => (
            <Link
              key={to}
              to={to}
              className="outline-none hover:text-brand-green focus-visible:text-brand-green max-sm:hidden"
            >
              {label}
            </Link>
          ))}
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
