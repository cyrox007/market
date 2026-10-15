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
      {/* Ниже 980 заголовка на макетах нет — остаётся для поисковиков и экранного диктора */}
      <div className="mt-7 flex flex-wrap items-baseline gap-x-3 gap-y-2 max-md:mt-0">
        <h1 className="text-24 font-bold text-ink max-md:sr-only">
          {title}
        </h1>
        {/* Ниже 980 число стоит под плашками — ProductCount в CatalogListing */}
        <ProductCount total={total} className="font-medium md:text-14 max-md:hidden" />
      </div>
    </header>
  );
}

/** «226 товаров»; null — ещё не загружено */
export function ProductCount({ total, className }: { total: number | null; className?: string }) {
  if (total === null) return null;
  return (
    <span className={`text-16 text-ink-secondary max-vsm:text-14 ${className ?? ''}`}>
      {formatNumber(total)} {plural(total, PRODUCTS)}
    </span>
  );
}
