import { Link } from 'react-router-dom';
import { cn } from '../../../../lib/cn';

export interface CategoryCardItem {
  id: string | number;
  name: string;
  to: string;
  image?: string | null;
}

export default function CategoryCard({
  item,
  className,
}: {
  item: CategoryCardItem;
  className?: string;
}) {
  return (
    <Link
      to={item.to}
      className={cn('group flex flex-col gap-2 outline-none', className)}
      aria-label={item.name}
    >
      <div className="aspect-[188/149] overflow-hidden rounded-card bg-surface-grey max-vsm:aspect-auto max-vsm:h-[149px]">
        {item.image ? (
          <img
            src={item.image}
            alt=""
            loading="lazy"
            className="size-full object-cover transition-transform duration-slow group-hover:scale-105"
          />
        ) : null}
      </div>
      <span className="text-14 text-ink group-hover:text-brand-green group-focus-visible:text-brand-green">
        {item.name}
      </span>
    </Link>
  );
}
