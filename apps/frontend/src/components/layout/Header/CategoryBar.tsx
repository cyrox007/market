import { Link } from 'react-router-dom';
import { Badge } from '../../ui/primitives';
import { cn } from '../../../lib/cn';
import { HEADER_CONTAINER } from './container';

export interface HeaderCategory {
  to: string;
  label: string;
  /** Красная подпись со своим бейджем — как «Распродажа  до −60%» */
  accent?: boolean;
  badge?: string;
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

/**
 * Нижняя полоса шапки: строка категорий.
 *
 * Замерено с макета 07.09.2026: высота **35**, пункты разложены `justify-between`,
 * как и крупные блоки основной полосы.
 *
 * Адаптив: ниже 980 остаются «Распродажа» и семь категорий, ниже 769 — она же
 * и пять категорий, ниже 550 полоса исчезает целиком.
 *
 * ⚠️ Не замерено: размер шрифта (стоит 14) и отступ бейджа от слова «Распродажа»
 * (стоит 8). Бейдж — размер `xs` с радиусом 6, он как раз замерен.
 */
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
          // Ниже 980 остаются «Распродажа» плюс семь категорий, то есть восемь
          // пунктов, последний — «Матрасы». Владелец назвал число 7, а на его же
          // скриншоте строка доходит до «Матрасов»: сходится, если «Распродажа»
          // считается промо-ссылкой, а не категорией. Если имелось в виду ровно
          // семь пунктов — поменять 9 на 8.
          //
          // Прячем стилем, а не срезаем массив: срез дал бы разную разметку на
          // сервере и в браузере, то есть рассинхрон при гидрации.
          'max-md:[&>*:nth-child(n+9)]:hidden',
          // Ниже 769 — «Распродажа» и пять категорий, шесть пунктов. Число
          // названо владельцем как «5»; трактуем так же, как раньше трактовали
          // «7» → восемь пунктов: «Распродажа» промо-ссылка, а не категория.
          // Если имелось в виду ровно пять пунктов — поменять 7 на 6.
          'max-sm:[&>*:nth-child(n+7)]:hidden',
        )}
      >
        {categories.map(({ to, label, accent, badge }) => (
          <Link
            key={to}
            to={to}
            className={cn(
              'inline-flex items-center gap-2 whitespace-nowrap outline-none',
              accent ? 'text-brand-red' : 'text-ink hover:text-brand-green',
              'focus-visible:text-brand-green',
            )}
          >
            {label}
            {badge && (
              <Badge variant="soft" size="xs" shape="badge">
                {badge}
              </Badge>
            )}
          </Link>
        ))}
      </nav>
    </div>
  );
}
