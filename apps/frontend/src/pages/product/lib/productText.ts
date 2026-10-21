import type { ProductDetail, ProductVariant } from '../../../lib/api';

export type ProductSpecification = { name: string; value: string; slug: string };

/** API отдаёт характеристики объектом или массивом — приводим к массиву */
export function getSpecificationsArray(
  specifications:
    ProductDetail['specifications'] | ProductVariant['specifications'] | null | undefined,
  specificationNames?: Record<string, string>,
): ProductSpecification[] {
  if (!specifications) return [];
  if (Array.isArray(specifications)) return specifications as ProductSpecification[];
  const names = specificationNames ?? {};
  return Object.entries(specifications).map(([slug, value]) => ({
    slug,
    name: names[slug] ?? slug,
    value: String(value ?? ''),
  }));
}

export function hasMeaningfulProductText(value: string | null | undefined): boolean {
  if (!value) return false;
  return (
    value
      .replace(/<[^>]+>/g, ' ')
      .replace(/&nbsp;/gi, ' ')
      .replace(/\s+/g, ' ')
      .trim().length > 0
  );
}

export const formatPrice = (price: number) => new Intl.NumberFormat('ru-RU').format(price) + ' ₽';
