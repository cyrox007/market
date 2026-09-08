import { Link } from 'react-router-dom';
import { Badge } from '../../../ui/primitives';
import { cn } from '../../../../lib/cn';
import { DISABLED_LINK, HEADER_CONTAINER } from '../lib/container';

export interface HeaderCategory {
  to: string;
  label: string;
  /** Красная подпись со своим бейджем — как «Распродажа  до −60%» */
  accent?: boolean;
  badge?: string;
  /** Маршрута под раздел ещё нет: рисуем приглушённым и не кликаем */
  disabled?: boolean;
}

interface CategoryBarProps {
  categories?: HeaderCategory[];
  className?: string;
}

const DEFAULT_CATEGORIES: HeaderCategory[] = [
  { to: '/catalog/sale', label: 'Распродажа', accent: true, badge: 'до −60%' },
  { to: '/catalog/stock', label: 'Сток' },
  { to: '/catalog/divany', label: 'Диваны' },
  { to: '/catalog/krovati', label: 'Кровати' },
  { to: '/catalog/shkafy', label: 'Шкафы' },
  { to: '/catalog/kuhni', label: 'Кухни' },
  { to: '/catalog/stoly-i-stulya', label: 'Столы и стулья' },
  { to: '/catalog/matrasy', label: 'Матрасы' },
  { to: '/catalog/detskaya', label: 'Детская' },
  { to: '/catalog/prihozhie', label: 'Прихожие' },
  { to: '/catalog/gostinye', label: 'Гостиные' },
];

/** Нижняя полоса шапки. Замеры и адаптив — market-docs/13-header.md */
export default function CategoryBar({
  categories = DEFAULT_CATEGORIES,
  className,
}: CategoryBarProps) {
  return (
    <div className={cn('h-[35px] bg-surface text-14 max-vsm:hidden', className)}>
      <nav
        aria-label="Категории"
        className={cn(
          HEADER_CONTAINER,
          'flex h-full items-center justify-between',
          // Прячем стилем, а не срезом массива: срез даёт рассинхрон гидрации
          'max-md:[&>*:nth-child(n+9)]:hidden',
          'max-sm:[&>*:nth-child(n+7)]:hidden',
        )}
      >
        {categories.map(({ to, label, accent, badge, disabled }) => {
          const content = (
            <>
              {label}
              {badge && (
                <Badge variant="soft" size="xs" shape="badge">
                  {badge}
                </Badge>
              )}
            </>
          );

          const shared = 'inline-flex items-center gap-2 whitespace-nowrap';

          return disabled ? (
            // «Распродажа» держит акцент даже без маршрута: это промо-ссылка, а не раздел
            <span
              key={to}
              aria-disabled="true"
              className={cn(shared, accent ? 'cursor-default text-brand-red' : DISABLED_LINK)}
            >
              {content}
            </span>
          ) : (
            <Link
              key={to}
              to={to}
              className={cn(
                shared,
                'outline-none focus-visible:text-brand-green',
                accent ? 'text-brand-red' : 'text-ink hover:text-brand-green',
              )}
            >
              {content}
            </Link>
          );
        })}
      </nav>
    </div>
  );
}
