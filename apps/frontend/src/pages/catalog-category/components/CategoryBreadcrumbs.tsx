import { Link } from 'react-router-dom';
import { ChevronRight } from 'lucide-react';

interface CategoryBreadcrumbsProps {
  name: string;
}

export default function CategoryBreadcrumbs({ name }: CategoryBreadcrumbsProps) {
  return (
    <nav
      aria-label="Хлебные крошки"
      className="flex min-w-0 items-center gap-2 overflow-hidden text-14 text-ink-secondary max-vsm:gap-1.5 max-vsm:text-12"
    >
      <Link to="/" className="shrink-0 transition-colors hover:text-brand-green">
        Главная
      </Link>
      <ChevronRight className="size-4 shrink-0 max-vsm:size-3.5" aria-hidden />
      <Link to="/catalog" className="shrink-0 transition-colors hover:text-brand-green">
        Каталог
      </Link>
      <ChevronRight className="size-4 shrink-0 max-vsm:size-3.5" aria-hidden />
      <span className="truncate text-ink-secondary">{name}</span>
    </nav>
  );
}
