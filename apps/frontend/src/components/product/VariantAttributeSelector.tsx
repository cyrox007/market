import type { VariationAttributeOption, VariationAttributeValue } from '../../lib/api';

interface VariantAttributeSelectorProps {
  attribute: VariationAttributeOption;
  selectedValueSlug: string | null;
  isValueAvailable: (valueSlug: string) => boolean;
  onSelect: (valueSlug: string, isDisabledClick?: boolean) => void;
  isDisabled?: boolean;
  /** Атрибут не применим к выбранной вариации (у неё нет этого параметра) — блок неактивен */
  inactive?: boolean;
}

/** Универсальный селектор одного атрибута вариации: цвет — свачи, остальные — кнопки с названием */
export default function VariantAttributeSelector({
  attribute,
  selectedValueSlug,
  isValueAvailable,
  onSelect,
  isDisabled = false,
  inactive = false,
}: VariantAttributeSelectorProps) {
  const { attribute_slug, attribute_name, values } = attribute;
  const isColor = attribute_slug === 'color';

  if (!values?.length) return null;

  const displayLabel = attribute_name || attribute_slug;
  const selectedVal = values.find((v: VariationAttributeValue) => (v.slug ?? '') === selectedValueSlug);
  const selectedDisplay = selectedVal?.name ?? selectedVal?.slug ?? selectedValueSlug ?? `Выберите ${displayLabel.toLowerCase()}`;

  if (inactive) {
    return (
      <div className="opacity-60 pointer-events-none">
        <label className="block text-sm font-medium mb-2 text-gray-500">
          {displayLabel}: <span className="text-gray-400 font-normal">—</span>
        </label>
        <div className="flex gap-2 flex-wrap">
          {values.slice(0, isColor ? 5 : 3).map((v: VariationAttributeValue, index: number) => (
            <div
              key={String(v.slug ?? v.id ?? index)}
              className={isColor ? 'w-10 h-10 rounded-lg border-2 border-gray-200 bg-gray-100' : 'px-4 py-2.5 rounded-lg border-2 border-gray-200 bg-gray-100 text-sm text-gray-400'}
            />
          ))}
        </div>
      </div>
    );
  }

  if (isColor) {
    return (
      <div>
        <label className="block text-sm font-medium mb-2">
          {displayLabel}: <span className="text-gray-600 font-normal">{selectedDisplay}</span>
        </label>
        <div className="flex gap-2 flex-wrap">
          {values.map((v: VariationAttributeValue, index: number) => {
            const slug = v.slug ?? (v as { value?: string }).value ?? '';
            const key = String((slug || v.name || v.id) ?? `val-${index}`);
            const isSelected = slug === selectedValueSlug && selectedValueSlug != null;
            const unavailable = !isValueAvailable(slug);
            return (
              <button
                key={key}
                onClick={() => {
                  if (isDisabled && !unavailable) return;
                  onSelect(slug, unavailable);
                }}
                disabled={isDisabled && !unavailable}
                className={`w-10 h-10 rounded-lg border-2 transition-all ${
                  isSelected
                    ? 'border-red-600 scale-110 ring-2 ring-red-200 cursor-default'
                    : unavailable
                    ? 'border-gray-200 cursor-pointer hover:opacity-60'
                    : 'border-gray-300 hover:border-red-600 cursor-pointer'
                } ${isDisabled && !unavailable ? 'opacity-50 cursor-wait' : ''}`}
                style={{
                  backgroundColor: v.code || '#f5f5f5',
                  opacity: unavailable ? 0.35 : isDisabled && !unavailable ? 0.5 : 1,
                }}
                title={v.name ?? slug}
              />
            );
          })}
        </div>
      </div>
    );
  }


  return (
    <div>
      <label className="block text-sm font-medium mb-2">
        {displayLabel}: <span className="text-gray-600 font-normal">{selectedDisplay}</span>
      </label>
      <div className="flex gap-2 flex-wrap">
        {values.map((v: VariationAttributeValue, index: number) => {
          const slug = v.slug ?? (v as { value?: string }).value ?? '';
          const key = String((slug || v.name || v.id) ?? `val-${index}`);
          const isSelected = slug === selectedValueSlug && selectedValueSlug != null;
          const unavailable = !isValueAvailable(slug);
          const label = v.name ?? v.slug ?? (v as { value?: string }).value ?? slug;
          return (
            <button
              key={key}
              onClick={() => {
                if (isDisabled && !unavailable) return;
                onSelect(slug, unavailable);
              }}
              disabled={isDisabled && !unavailable}
              className={`px-4 py-2.5 rounded-lg border-2 text-sm font-medium whitespace-nowrap transition-all ${
                isSelected
                  ? 'border-red-600 bg-red-50 text-red-600 font-semibold cursor-default'
                  : unavailable
                  ? 'border-gray-200 text-gray-400 cursor-pointer hover:opacity-60'
                  : 'border-gray-300 hover:border-red-600 cursor-pointer'
              } ${isDisabled && !unavailable ? 'opacity-50 cursor-wait' : ''}`}
              style={{ opacity: unavailable ? 0.35 : isDisabled && !unavailable ? 0.5 : 1 }}
              title={String(label)}
            >
              {label}
            </button>
          );
        })}
      </div>
    </div>
  );
}
