/** Частые slug атрибутов → подпись на русском (если API не прислал attribute_name) */
const ATTRIBUTE_SLUG_LABELS: Record<string, string> = {
  color: 'Цвет',
  size: 'Размер',
  komplekt: 'Комплект',
  variant: 'Вариант',
};

function humanizeSlug(slug: string): string {
  const normalized = slug.replace(/[-_]+/g, ' ').trim();
  if (!normalized) return slug;
  return normalized.charAt(0).toUpperCase() + normalized.slice(1);
}

export function getAttributeLabel(
  attr: { attribute_slug: string; attribute_name?: string | null },
  nameBySlug?: Record<string, string>,
): string {
  const fromApi = attr.attribute_name?.trim();
  if (fromApi) return fromApi;
  if (nameBySlug?.[attr.attribute_slug]?.trim()) return nameBySlug[attr.attribute_slug].trim();
  if (ATTRIBUTE_SLUG_LABELS[attr.attribute_slug]) return ATTRIBUTE_SLUG_LABELS[attr.attribute_slug];
  return humanizeSlug(attr.attribute_slug);
}

export function getValueLabel(attr: { value_slug: string; value_name?: string | null }): string {
  const fromApi = attr.value_name?.trim();
  if (fromApi) return fromApi;
  return attr.value_slug;
}
