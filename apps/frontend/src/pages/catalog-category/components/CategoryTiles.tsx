import { Link } from 'react-router-dom';
import { ImageIcon } from 'lucide-react';
import type { Category } from '../../../lib/api';

interface CategoryTilesProps {
  categories: Category[];
  onPrefetch?: (slug: string) => void;
}

export default function CategoryTiles({
  categories,
  onPrefetch,
}: CategoryTilesProps) {
  if (!categories.length) return null;

  return (
    <div className="grid grid-cols-2 gap-x-6 gap-y-6 vsm:grid-cols-4 md:grid-cols-6 max-vsm:gap-x-3 max-vsm:gap-y-5">
      {categories.map((category) => {
        const image =
          category.image_thumb ||
          category.image_hd ||
          category.image_fullhd ||
          category.image;

        return (
          <Link
            key={category.id}
            to={`/catalog/${category.slug}`}
            className="group min-w-0 rounded-[18px] outline-none transition-transform duration-fast hover:-translate-y-0.5 focus-visible:-translate-y-0.5"
            onMouseEnter={() => onPrefetch?.(category.slug)}
            onFocus={() => onPrefetch?.(category.slug)}
          >
            <div className="aspect-[188/174] overflow-hidden rounded-[18px] bg-surface-grey p-2 transition-shadow duration-fast group-hover:shadow-[0_6px_18px_rgba(20,20,20,0.16)] group-focus-visible:shadow-[0_6px_18px_rgba(20,20,20,0.16)] max-vsm:rounded-[16px] max-vsm:p-1.5">
              {image ? (
                <img
                  src={image}
                  alt={category.name}
                  className="h-full w-full rounded-[12px] object-cover"
                  loading="lazy"
                  decoding="async"
                  sizes="(max-width: 549px) 45vw, (max-width: 979px) 23vw, 188px"
                />
              ) : (
                <div className="flex h-full w-full items-center justify-center rounded-[12px] bg-surface text-ink-secondary">
                  <ImageIcon className="size-8" />
                </div>
              )}
            </div>

            <div className="mt-2 line-clamp-2 text-16 font-medium leading-[1.25] text-ink max-vsm:text-14">
              {category.name}
            </div>
          </Link>
        );
      })}
    </div>
  );
}
