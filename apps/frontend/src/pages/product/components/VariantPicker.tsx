import { useId } from 'react';
import { ChevronDown } from 'lucide-react';
import { cn } from '../../../lib/cn';
import type { VariantAttributeView } from '../hooks/useVariantSelection';

interface Props {
  attributes: VariantAttributeView[];
  onSelect: (attributeSlug: string, valueSlug: string) => void;
  /** Пока перезагружается товар (смена региона) */
  disabled?: boolean;
}

const UNAVAILABLE = 'нет в этом сочетании';

const lowerFirst = (s: string) => s.charAt(0).toLowerCase() + s.slice(1);

/** Серый блок выбора варианта: цвет — квадраты, прочее — списки (Figma 669:43415) */
export default function VariantPicker({ attributes, onSelect, disabled = false }: Props) {
  const id = useId();
  if (attributes.length === 0) return null;

  return (
    <div className="flex flex-col gap-4 rounded-btn bg-surface-grey p-4">
      {attributes.map((attr) =>
        attr.isColor ? (
          <div key={attr.slug} className="flex flex-col gap-2">
            <p className="text-14 font-medium text-ink">
              {attr.name}
              {attr.selectedName && `: ${lowerFirst(attr.selectedName)}`}
            </p>
            <div className="flex flex-wrap gap-2">
              {attr.values.map((value) => (
                <button
                  key={value.slug}
                  type="button"
                  aria-pressed={value.selected}
                  aria-label={value.available ? value.name : `${value.name}, ${UNAVAILABLE}`}
                  title={value.name}
                  disabled={disabled}
                  onClick={() => !value.selected && onSelect(attr.slug, value.slug)}
                  className={cn(
                    'flex size-[42px] items-center justify-center rounded-badge border p-0.5',
                    'transition-[border-color,opacity] duration-200 motion-reduce:transition-none',
                    value.selected
                      ? 'border-surface-stroke'
                      : 'border-surface-stroke/[0.08] hover:border-surface-stroke/40',
                    !value.available && 'opacity-40',
                    disabled && 'cursor-wait',
                  )}
                >
                  <span
                    className="size-9 rounded-swatch border border-surface-stroke/[0.08]"
                    style={{ backgroundColor: value.code ?? undefined }}
                  />
                </button>
              ))}
            </div>
          </div>
        ) : (
          <div key={attr.slug} className="flex flex-col gap-2">
            <label htmlFor={`${id}-${attr.slug}`} className="text-14 font-medium text-ink">
              {attr.name}:
            </label>
            <span className="relative">
              <select
                id={`${id}-${attr.slug}`}
                value={attr.values.find((v) => v.selected)?.slug ?? ''}
                disabled={disabled}
                onChange={(e) => onSelect(attr.slug, e.target.value)}
                className={cn(
                  'h-12 w-full cursor-pointer appearance-none rounded-btn border border-surface-stroke/[0.08]',
                  'bg-surface pl-5 pr-12 text-14 text-ink outline-none transition-colors',
                  'hover:border-surface-stroke/40 focus-visible:border-surface-stroke',
                  'disabled:cursor-wait',
                )}
              >
                {attr.values.map((value) => (
                  <option
                    key={value.slug}
                    value={value.slug}
                    className={value.available ? undefined : 'text-ink-secondary'}
                  >
                    {value.available ? value.name : `${value.name}, ${UNAVAILABLE}`}
                  </option>
                ))}
              </select>
              <ChevronDown
                aria-hidden="true"
                className="pointer-events-none absolute right-5 top-1/2 size-6 -translate-y-1/2 text-ink"
              />
            </span>
          </div>
        ),
      )}
    </div>
  );
}
