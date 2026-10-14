import type { FiltersMeta } from '../api';
import {
  LIST_KEYS,
  MANUFACTURER_ATTRIBUTE,
  RANGE_KEYS,
  type CatalogQuery,
  type ListKey,
  type Range,
  type RangeKey,
} from './types';

export interface FilterChip {
  id: string;
  label: string;
}

export interface ChipNames {
  meta?: FiltersMeta | null;
  /** Подкатегории текущей категории — в meta.filters их нет */
  categories?: { slug: string; name: string }[];
}

const RANGE_LABELS: Record<RangeKey, { title: string; unit: string }> = {
  price: { title: 'Цена', unit: '₽' },
  width: { title: 'Ширина', unit: 'мм' },
  depth: { title: 'Глубина', unit: 'мм' },
  height: { title: 'Высота', unit: 'мм' },
};

const SEPARATOR = ':';

const digits = (n: number) => String(n).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');

function rangeLabel(key: RangeKey, { min, max }: Range): string {
  const { title, unit } = RANGE_LABELS[key];
  if (min !== undefined && max !== undefined)
    return `${title} ${digits(min)}–${digits(max)} ${unit}`;
  if (min !== undefined) return `${title} от ${digits(min)} ${unit}`;
  return `${title} до ${digits(max!)} ${unit}`;
}

type Named = { slug?: string | null; name?: string | null };

function nameIn(list: Named[] | undefined, slug: string): string {
  return list?.find((item) => item.slug === slug)?.name || slug;
}

/** Производители — значения характеристики proizvoditel; meta.filters.manufacturers бэкенд не заполняет */
export function manufacturerOptions(meta: FiltersMeta | null | undefined) {
  return (
    meta?.attributes.find((a) => a.slug === MANUFACTURER_ATTRIBUTE)?.values ??
    meta?.manufacturers ??
    []
  );
}

function listNames(names: ChipNames): Record<ListKey, Named[] | undefined> {
  return {
    manufacturers: manufacturerOptions(names.meta),
    categories: names.categories,
    colors: names.meta?.colors,
    sizes: names.meta?.sizes,
  };
}

/** Порядок плашек: диапазоны, «По акции», списки, атрибуты */
export function filterChips(query: CatalogQuery, names: ChipNames = {}): FilterChip[] {
  const chips: FilterChip[] = [];
  for (const key of RANGE_KEYS) {
    const range = query[key];
    if (range.min !== undefined || range.max !== undefined) {
      chips.push({ id: key, label: rangeLabel(key, range) });
    }
  }
  if (query.onSale) chips.push({ id: 'on_sale', label: 'По акции' });

  const lists = listNames(names);
  for (const key of Object.keys(LIST_KEYS) as ListKey[]) {
    for (const slug of query[key]) {
      chips.push({ id: [key, slug].join(SEPARATOR), label: nameIn(lists[key], slug) });
    }
  }

  for (const [attr, values] of Object.entries(query.attributes)) {
    const attrValues = names.meta?.attributes.find((a) => a.slug === attr)?.values;
    for (const slug of values) {
      chips.push({ id: ['attr', attr, slug].join(SEPARATOR), label: nameIn(attrValues, slug) });
    }
  }
  return chips;
}

export function activeFilterCount(query: CatalogQuery): number {
  return filterChips(query).length;
}

const without = (values: string[], slug: string) => values.filter((v) => v !== slug);

export function removeChip(query: CatalogQuery, chipId: string): CatalogQuery {
  const next: CatalogQuery = { ...query, page: 1 };
  const [kind, ...rest] = chipId.split(SEPARATOR);

  if ((RANGE_KEYS as readonly string[]).includes(kind)) next[kind as RangeKey] = {};
  else if (kind === 'on_sale') next.onSale = false;
  else if (kind in LIST_KEYS) next[kind as ListKey] = without(query[kind as ListKey], rest[0]);
  else if (kind === 'attr') {
    const [attr, slug] = rest;
    const { [attr]: values = [], ...others } = query.attributes;
    const left = without(values, slug);
    next.attributes = left.length ? { ...others, [attr]: left } : others;
  }
  return next;
}

export function resetFilters(query: CatalogQuery): CatalogQuery {
  return filterChips(query).reduce((next, chip) => removeChip(next, chip.id), query);
}
