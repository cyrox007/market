import CategoryCarousel from './CategoryCarousel';
import type { CategoryCardItem } from './CategoryCard';
import { useCategoryTree } from '../../../../hooks/useCategoryTree';
import { DEMO_CATEGORY_IMAGES, DEMO_EXTRA_CATEGORIES } from '../PromoBoard/lib/demo-promo';

const toItem = (c: {
  id: string | number;
  name: string;
  slug: string;
  image?: string | null;
}): CategoryCardItem => ({
  id: c.id,
  name: c.name,
  to: `/catalog/${c.slug}`,
  image: c.image || DEMO_CATEGORY_IMAGES[c.slug],
});

export default function CategoryCarouselConnected() {
  const { categories } = useCategoryTree();

  const items: CategoryCardItem[] = categories.map((category) =>
    toItem({
      id: category.id,
      name: category.name,
      slug: category.slug,
      image: category.image_thumb || category.image_hd || category.image,
    }),
  );

  const withDemo = items.length >= 9 ? items : [...items, ...DEMO_EXTRA_CATEGORIES.map(toItem)];

  return <CategoryCarousel items={withDemo} />;
}
