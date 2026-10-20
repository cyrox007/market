import { Fragment, type ReactNode } from 'react';
import { AccordionSection } from '../../../../components/ui/primitives';
import type { FiltersMeta } from '../../../../lib/api';
import { MANUFACTURER_ATTRIBUTE, type CatalogQuery } from '../../../../lib/catalog-query';
import { filterAvailability } from '../fields/availability';
import { basicFilters, type BasicFilterKey } from '../fields/basicFilters';
import CheckboxList, { type CheckboxOption } from './CheckboxList';

interface FilterSectionsProps {
  draft: CatalogQuery;
  set: (patch: Partial<CatalogQuery>) => void;
  meta: FiltersMeta | null;
  subcategories: CheckboxOption[];
}

interface Section {
  key: string;
  title: string;
  defaultOpen?: boolean;
  body: ReactNode;
}

type MetaOption = {
  slug?: string | null;
  name?: string | null;
  value?: string | null;
  count?: number;
};

const SECTION_TITLES: Record<BasicFilterKey, string> = {
  price: 'Цена, ₽',
  dimensions: 'Габариты, мм',
  manufacturers: 'Производители',
};

const toOptions = (items: MetaOption[]): CheckboxOption[] =>
  items.flatMap((i) => {
    const slug = i.slug ?? i.value;
    return slug ? [{ slug, name: i.name || slug, count: i.count }] : [];
  });

/** Разделы «Все фильтры» по макету 730:53152; что раскрыто и почему есть цвет и размер — market-docs/48 */
export default function FilterSections({ draft, set, meta, subcategories }: FilterSectionsProps) {
  const available = filterAvailability(meta, draft);

  const sections: Section[] = basicFilters(available, draft, set).map(({ key, body }) => ({
    key,
    title: SECTION_TITLES[key],
    body,
  }));

  if (subcategories.length) {
    sections.push({
      key: 'category',
      title: 'Категория',
      body: (
        <CheckboxList
          options={subcategories}
          value={draft.categories}
          onChange={(categories) => set({ categories })}
        />
      ),
    });
  }
  if (available.onSale) {
    sections.push({
      key: 'sale',
      title: 'Акция',
      body: (
        <CheckboxList
          options={[{ slug: 'on_sale', name: 'По акции' }]}
          value={draft.onSale ? ['on_sale'] : []}
          onChange={(v) => set({ onSale: v.length > 0 })}
        />
      ),
    });
  }
  for (const attr of meta?.attributes ?? []) {
    // Производители — своим разделом выше
    if (!attr.values.length || attr.slug === MANUFACTURER_ATTRIBUTE) continue;
    sections.push({
      key: `attr:${attr.slug}`,
      title: attr.name,
      defaultOpen: false,
      body: (
        <CheckboxList
          options={toOptions(attr.values)}
          value={draft.attributes[attr.slug] ?? []}
          onChange={(values) => {
            const others = Object.fromEntries(
              Object.entries(draft.attributes).filter(([slug]) => slug !== attr.slug),
            );
            set({ attributes: values.length ? { ...others, [attr.slug]: values } : others });
          }}
        />
      ),
    });
  }
  if (meta?.colors.length) {
    sections.push({
      key: 'colors',
      title: 'Цвет',
      defaultOpen: false,
      body: (
        <CheckboxList
          options={toOptions(meta.colors)}
          value={draft.colors}
          onChange={(colors) => set({ colors })}
        />
      ),
    });
  }
  if (meta?.sizes.length) {
    sections.push({
      key: 'sizes',
      title: 'Размер',
      defaultOpen: false,
      body: (
        <CheckboxList
          options={toOptions(meta.sizes)}
          value={draft.sizes}
          onChange={(sizes) => set({ sizes })}
        />
      ),
    });
  }

  // Планшет: две колонки без разделителей, между рядами 28 (макет Tablet 768)
  return (
    <div className="grid grid-cols-1 gap-x-8 vsm:max-md:grid-cols-2 vsm:max-md:gap-y-7">
      {sections.map((section, index) => (
        <Fragment key={section.key}>
          {index > 0 && <hr className="my-6 border-surface-border vsm:max-md:hidden" />}
          <AccordionSection
            title={section.title}
            defaultOpen={section.defaultOpen ?? true}
            // От 980 (Figma 730:53152) заголовок и пара полей — 280, ползунок и разделитель — на всю
            // ширину; ниже — всё на ширину колонки (макеты Mobile 375, Tablet 768)
            className="min-w-0"
            headerClassName="md:w-[280px] md:max-w-full"
          >
            {section.body}
          </AccordionSection>
        </Fragment>
      ))}
    </div>
  );
}
