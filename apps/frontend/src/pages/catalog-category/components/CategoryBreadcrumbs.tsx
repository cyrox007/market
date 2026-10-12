import { Fragment } from 'react';
import { Link } from 'react-router-dom';
import { ChevronRight } from 'lucide-react';

interface CategoryBreadcrumbsProps {
  name: string;
  /** Родители от старшего к младшему — между «Каталогом» и текущей */
  trail?: { name: string; to: string }[];
}

const LINK = 'shrink-0 text-ink transition-colors hover:text-ink-secondary';
const CHEVRON = 'size-4 shrink-0 max-vsm:size-3.5';

/** От 550 — полный путь; ниже — «Главная › … › текущая», «…» ведёт к ближайшему родителю */
export default function CategoryBreadcrumbs({ name, trail = [] }: CategoryBreadcrumbsProps) {
  const middle = [{ name: 'Каталог', to: '/catalog' }, ...trail];
  const parent = middle[middle.length - 1];

  return (
    <nav
      aria-label="Хлебные крошки"
      className="flex min-w-0 items-center gap-2 overflow-hidden text-14 text-ink-secondary max-vsm:gap-1.5 max-vsm:text-12"
    >
      <Link to="/" className={LINK}>
        Главная
      </Link>
      {middle.map((item) => (
        <Fragment key={item.to}>
          <ChevronRight className={`${CHEVRON} max-vsm:hidden`} aria-hidden />
          <Link to={item.to} className={`${LINK} max-vsm:hidden`}>
            {item.name}
          </Link>
        </Fragment>
      ))}
      <ChevronRight className={`${CHEVRON} vsm:hidden`} aria-hidden />
      <Link to={parent.to} aria-label={`Назад: ${parent.name}`} className={`${LINK} vsm:hidden`}>
        …
      </Link>
      <ChevronRight className={CHEVRON} aria-hidden />
      <span className="truncate text-ink-secondary">{name}</span>
    </nav>
  );
}
