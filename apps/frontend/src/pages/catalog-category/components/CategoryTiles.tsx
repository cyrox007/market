import { Link } from 'react-router-dom';
import { ImageIcon } from 'lucide-react';
import type { Category } from '../../../lib/api';

interface CategoryTilesProps {
  categories: Category[];
  isRooms?: boolean;
  onPrefetch?: (slug: string) => void;
}

export default function CategoryTiles({
  categories,
  isRooms = false,
  onPrefetch,
}: CategoryTilesProps) {
  if (!categories.length) return null;

  const basePath = isRooms ? '/rooms' : '/catalog';

  return (
    <div
      className="
        grid grid-cols-2 gap-x-4 gap-y-5
        vsm:grid-cols-3 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6
        max-vsm:gap-x-3 max-vsm:gap-y-4
      "
    >
      {categories.map((category) => {
        const image =
          category.image_thumb || category.image_hd || category.image_fullhd || category.image;

        return (
          <Link
            key={category.id}
            to={`${basePath}/${category.slug}`}
            className="group min-w-0"
            onMouseEnter={() => onPrefetch?.(category.slug)}
            onFocus={() => onPrefetch?.(category.slug)}
          >
            <div className="aspect-square overflow-hidden rounded-card bg-surface-grey">
              {image ? (
                <img
                  src={image}
                  alt={category.name}
                  className="h-full w-full object-cover transition-transform duration-slow group-hover:scale-[1.025]"
                  loading="lazy"
                  decoding="async"
                />
              ) : (
                <div className="flex h-full w-full items-center justify-center text-ink-secondary">
                  <ImageIcon className="size-8" />
                </div>
              )}
            </div>
            <div className="mt-2 text-16 font-medium leading-tight text-ink max-vsm:text-14">
              {category.name}
            </div>
          </Link>
        );
      })}
    </div>
  );
}
