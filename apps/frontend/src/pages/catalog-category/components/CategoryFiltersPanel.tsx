import { X } from 'lucide-react';
import type { FiltersMeta } from '../../../lib/api';

interface CategoryFiltersPanelProps {
  open: boolean;
  filters: FiltersMeta | null;
  priceRange: [number, number];
  selectedColors: string[];
  selectedSizes: string[];
  selectedAttributes: Record<string, string[]>;
  onClose: () => void;
  onPriceRangeChange: (range: [number, number]) => void;
  onApplyPrice: () => void;
  onColorToggle: (slug: string) => void;
  onSizeToggle: (value: string) => void;
  onAttributeToggle: (attributeSlug: string, valueSlug: string) => void;
  onReset: () => void;
}

function FiltersSkeleton() {
  return (
    <div className="animate-pulse space-y-5">
      <div>
        <div className="mb-2 h-4 w-12 rounded bg-surface-grey" />
        <div className="mb-2 flex gap-2">
          <div className="h-9 flex-1 rounded-btn bg-surface-grey" />
          <div className="h-9 flex-1 rounded-btn bg-surface-grey" />
        </div>
        <div className="h-9 rounded-btn bg-surface-grey" />
      </div>
      <div>
        <div className="mb-2 h-4 w-14 rounded bg-surface-grey" />
        <div className="flex flex-wrap gap-2">
          {Array.from({ length: 6 }).map((_, index) => (
            <div key={index} className="size-8 rounded-full bg-surface-grey" />
          ))}
        </div>
      </div>
      {Array.from({ length: 3 }).map((_, index) => (
        <div key={index}>
          <div className="mb-2 h-4 w-24 rounded bg-surface-grey" />
          <div className="space-y-2">
            {Array.from({ length: 3 }).map((__, itemIndex) => (
              <div key={itemIndex} className="flex items-center gap-2">
                <div className="size-4 rounded bg-surface-grey" />
                <div className="h-4 max-w-[80%] flex-1 rounded bg-surface-grey" />
              </div>
            ))}
          </div>
        </div>
      ))}
    </div>
  );
}

export default function CategoryFiltersPanel({
  open,
  filters,
  priceRange,
  selectedColors,
  selectedSizes,
  selectedAttributes,
  onClose,
  onPriceRangeChange,
  onApplyPrice,
  onColorToggle,
  onSizeToggle,
  onAttributeToggle,
  onReset,
}: CategoryFiltersPanelProps) {
  return (
    <aside
      className={[
        open ? 'fixed inset-0 z-50 overflow-y-auto bg-surface' : 'hidden',
        'md:block md:w-[260px] md:shrink-0',
      ].join(' ')}
      aria-label="Фильтры каталога"
    >
      <div className="flex items-center justify-between border-b border-surface-border p-4 md:hidden">
        <h2 className="text-18 font-semibold text-ink">Фильтры</h2>
        <button
          type="button"
          onClick={onClose}
          aria-label="Закрыть фильтры"
          className="flex size-9 items-center justify-center rounded-full hover:bg-surface-grey"
        >
          <X className="size-5" />
        </button>
      </div>

      <div className="bg-surface p-4 md:sticky md:top-4 md:rounded-card md:border md:border-surface-border md:p-5">
        <h2 className="mb-5 hidden text-18 font-semibold text-ink md:block">Фильтры</h2>

        {!filters ? (
          <FiltersSkeleton />
        ) : (
          <>
            <section className="mb-6">
              <h3 className="mb-2 text-14 font-semibold text-ink">Цена</h3>
              <div className="mb-2 grid grid-cols-2 gap-2">
                <input
                  type="number"
                  value={priceRange[0]}
                  aria-label="Цена от"
                  onChange={(event) => onPriceRangeChange([Number(event.target.value), priceRange[1]])}
                  className="h-10 min-w-0 rounded-btn border border-surface-border bg-surface px-3 text-14 outline-none focus:border-ink"
                />
                <input
                  type="number"
                  value={priceRange[1]}
                  aria-label="Цена до"
                  onChange={(event) => onPriceRangeChange([priceRange[0], Number(event.target.value)])}
                  className="h-10 min-w-0 rounded-btn border border-surface-border bg-surface px-3 text-14 outline-none focus:border-ink"
                />
              </div>
              <button
                type="button"
                onClick={onApplyPrice}
                className="h-10 w-full rounded-pill bg-brand-yellow px-4 text-14 font-medium text-ink hover:bg-brand-green hover:text-ink-inverse"
              >
                Применить
              </button>
            </section>

            {filters.colors.length ? (
              <section className="mb-6">
                <h3 className="mb-2 text-14 font-semibold text-ink">Цвет</h3>
                <div className="flex flex-wrap gap-2">
                  {filters.colors.map((color, index) => {
                    const disabled = color.count === 0;
                    const slug = color.slug || color.name || '';
                    const selected = selectedColors.includes(slug);

                    return (
                      <button
                        key={color.slug || color.name || index}
                        type="button"
                        disabled={disabled}
                        onClick={() => !disabled && onColorToggle(slug)}
                        aria-pressed={selected}
                        title={disabled ? 'Нет товаров' : color.name || undefined}
                        className={[
                          'size-8 rounded-full border-2 transition-transform',
                          disabled
                            ? 'cursor-not-allowed border-surface-border opacity-40'
                            : selected
                              ? 'scale-110 border-ink'
                              : 'border-surface-border hover:border-ink',
                        ].join(' ')}
                        style={{ backgroundColor: color.code || '#E8E5E1' }}
                      />
                    );
                  })}
                </div>
              </section>
            ) : null}

            {filters.sizes.length ? (
              <section className="mb-6">
                <h3 className="mb-2 text-14 font-semibold text-ink">Размер</h3>
                <div className="flex flex-wrap gap-2">
                  {filters.sizes.map((size, index) => {
                    const disabled = size.count === 0;
                    const value = size.value || size.slug || '';
                    const selected = selectedSizes.includes(value);

                    return (
                      <button
                        key={size.slug || size.value || index}
                        type="button"
                        disabled={disabled}
                        onClick={() => !disabled && onSizeToggle(value)}
                        aria-pressed={selected}
                        className={[
                          'min-h-9 rounded-pill border px-3 text-14',
                          disabled
                            ? 'cursor-not-allowed border-surface-border text-ink-secondary opacity-50'
                            : selected
                              ? 'border-ink bg-ink text-ink-inverse'
                              : 'border-surface-border text-ink hover:border-ink',
                        ].join(' ')}
                      >
                        {size.name || size.value}
                      </button>
                    );
                  })}
                </div>
              </section>
            ) : null}

            {filters.attributes.map((attribute) => (
              <section className="mb-6" key={attribute.slug}>
                <h3 className="mb-2 text-14 font-semibold text-ink">{attribute.name}</h3>
                <div className="space-y-2">
                  {attribute.values.map((value) => {
                    const disabled = value.count === 0;
                    const checked = (selectedAttributes[attribute.slug] || []).includes(value.slug);

                    return (
                      <label
                        key={value.slug}
                        className={[
                          'flex items-center gap-2 text-14',
                          disabled ? 'cursor-not-allowed opacity-45' : 'cursor-pointer',
                        ].join(' ')}
                      >
                        <input
                          type="checkbox"
                          checked={checked}
                          disabled={disabled}
                          onChange={() => !disabled && onAttributeToggle(attribute.slug, value.slug)}
                          className="size-4 accent-[#0A6044]"
                        />
                        <span>{value.name}</span>
                      </label>
                    );
                  })}
                </div>
              </section>
            ))}

            <button
              type="button"
              onClick={onReset}
              className="h-10 w-full rounded-pill border border-ink px-4 text-14 font-medium text-ink hover:bg-ink hover:text-ink-inverse"
            >
              Сбросить фильтры
            </button>
          </>
        )}
      </div>
    </aside>
  );
}
