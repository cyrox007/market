import type { Category } from '../../../lib/api';
import { plural } from '../../../lib/plural';
import { formatNumber } from '../../../components/ui/primitives/digits';
import CategoryBreadcrumbs from '../components/CategoryBreadcrumbs';

interface CatalogHeaderProps {
  category: Category | null;
  title: string;
  /** null — ещё не загружено */
  total: number | null;
}

const PRODUCTS = ['товар', 'товара', 'товаров'] as const;

/** Родители из category.parent: API отдаёт цепочку вложенными объектами */
function parentTrail(category: Category | null) {
  const trail: { name: string; to: string }[] = [];
  for (let p = category?.parent; p; p = p.parent) {
    trail.unshift({ name: p.name, to: p.full_path || `/catalog/${p.slug}` });
  }
  return trail;
}

export default function CatalogHeader({ category, title, total }: CatalogHeaderProps) {
  return (
    <header>
      <CategoryBreadcrumbs name={title} trail={parentTrail(category)} />
      <div className="mt-7 flex flex-wrap items-baseline gap-x-4 gap-y-2 max-md:mt-6 max-vsm:mt-5">
        <h1 className="font-display text-32 font-bold leading-none text-ink max-vsm:text-24">
          {title}
        </h1>
        {total !== null && (
          <span className="text-16 text-ink-secondary max-vsm:text-14">
            {formatNumber(total)} {plural(total, PRODUCTS)}
          </span>
        )}
      </div>
    </header>
  );
}
