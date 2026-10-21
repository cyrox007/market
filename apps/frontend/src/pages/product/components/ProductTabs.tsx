import { useRef, type KeyboardEvent, type ReactNode } from 'react';
import { cn } from '../../../lib/cn';
import { hasMeaningfulProductText, type ProductSpecification } from '../lib/productText';
import ProductEmptyState from './ProductEmptyState';

export type ProductTabId = 'description' | 'specs' | 'reviews';

const TABS: { id: ProductTabId; label: string }[] = [
  { id: 'description', label: 'Описание' },
  { id: 'specs', label: 'Характеристики' },
  { id: 'reviews', label: 'Отзывы' },
];

interface Props {
  activeTab: ProductTabId;
  onTabChange: (tab: ProductTabId) => void;
  description: string | null | undefined;
  specifications: ProductSpecification[];
  reviewsCount: number;
  reviews: ReactNode;
}

export const PRODUCT_DETAILS_ID = 'product-details';

/** Все вкладки в разметке (для SSR и поиска), неактивные — hidden; market-docs/56 */
export default function ProductTabs({
  activeTab,
  onTabChange,
  description,
  specifications,
  reviewsCount,
  reviews,
}: Props) {
  const tabRefs = useRef<Partial<Record<ProductTabId, HTMLButtonElement | null>>>({});

  const onKeyDown = (event: KeyboardEvent) => {
    const step = { ArrowRight: 1, ArrowLeft: -1 }[event.key];
    const at = TABS.findIndex((tab) => tab.id === activeTab);
    const next =
      event.key === 'Home' ? 0 : event.key === 'End' ? TABS.length - 1 : step ? at + step : null;
    if (next === null) return;
    event.preventDefault();
    const id = TABS[(next + TABS.length) % TABS.length].id;
    onTabChange(id);
    tabRefs.current[id]?.focus();
  };

  const panels: Record<ProductTabId, ReactNode> = {
    description: hasMeaningfulProductText(description) ? (
      <div
        className="max-w-[714px] text-14 text-ink-secondary [&_p+p]:mt-3"
        dangerouslySetInnerHTML={{ __html: description! }}
      />
    ) : (
      <ProductEmptyState
        icon="description"
        title="Описания пока нет"
        text="Мы готовим описание этого товара. Если есть вопросы — позвоните нам, расскажем подробнее."
      />
    ),
    specs: specifications.length ? (
      <div className="max-w-[714px] text-14 font-medium">
        {specifications.map((spec) => (
          <div
            key={spec.slug}
            className="flex gap-2.5 rounded-[2px] px-3 py-2 even:bg-surface-grey/[0.54]"
          >
            <span className="w-[276px] shrink-0 text-ink-secondary max-vsm:w-[45%]">
              {spec.name}
            </span>
            <span className="min-w-0 flex-1 text-ink">{spec.value}</span>
          </div>
        ))}
      </div>
    ) : (
      <ProductEmptyState
        icon="specs"
        title="Характеристик пока нет"
        text="Размеры и материалы уточнит менеджер — контакты ниже."
      />
    ),
    reviews,
  };

  return (
    <div id={PRODUCT_DETAILS_ID} className="mb-12 scroll-mt-20">
      <div
        role="tablist"
        aria-label="О товаре"
        onKeyDown={onKeyDown}
        className="mb-3 flex gap-4 overflow-x-auto border-b border-surface-stroke/[0.08] [scrollbar-width:none]"
      >
        {TABS.map((tab) => {
          const active = tab.id === activeTab;
          return (
            <button
              key={tab.id}
              ref={(node) => {
                tabRefs.current[tab.id] = node;
              }}
              type="button"
              role="tab"
              id={`product-tab-${tab.id}`}
              aria-selected={active}
              aria-controls={`product-panel-${tab.id}`}
              tabIndex={active ? 0 : -1}
              onClick={() => onTabChange(tab.id)}
              className={cn(
                'relative shrink-0 whitespace-nowrap pb-1 text-16 font-semibold transition-colors duration-slow',
                active ? 'text-ink' : 'text-ink-secondary hover:text-ink',
              )}
            >
              {tab.label}
              {tab.id === 'reviews' && reviewsCount > 0 && (
                <span className="ml-1 font-normal text-ink-secondary">{reviewsCount}</span>
              )}
              <span
                aria-hidden
                className={cn(
                  'absolute inset-x-0 bottom-0 h-px origin-center bg-brand-yellow transition-transform duration-slow motion-reduce:transition-none',
                  active ? 'scale-x-100' : 'scale-x-0',
                )}
              />
            </button>
          );
        })}
      </div>

      {TABS.map((tab) => (
        <div
          key={tab.id}
          role="tabpanel"
          id={`product-panel-${tab.id}`}
          aria-labelledby={`product-tab-${tab.id}`}
          hidden={tab.id !== activeTab}
          // Высота пустой заглушки: короткое описание и «Отзывов пока нет» не двигают блоки ниже
          className={cn(
            'min-h-[346px] flex-col motion-safe:animate-photoIn max-vsm:min-h-[280px]',
            tab.id === activeTab ? 'flex' : 'hidden',
          )}
        >
          {panels[tab.id]}
        </div>
      ))}
    </div>
  );
}
