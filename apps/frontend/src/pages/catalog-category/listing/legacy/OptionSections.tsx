import type { FiltersMeta } from '../../../../lib/api';

interface OptionSectionsProps {
  meta: FiltersMeta;
  colors: string[];
  sizes: string[];
  attributes: Record<string, string[]>;
  onToggleColor: (slug: string) => void;
  onToggleSize: (value: string) => void;
  onToggleAttribute: (attr: string, value: string) => void;
}

/** Цвета, размеры, атрибуты: все опции категории, недоступные (count === 0) — серые */
export default function OptionSections({
  meta,
  colors,
  sizes,
  attributes,
  onToggleColor,
  onToggleSize,
  onToggleAttribute,
}: OptionSectionsProps) {
  return (
    <>
      {meta.colors?.length > 0 && (
        <div className="mb-5">
          <label className="block text-sm font-medium mb-2">Цвет</label>
          <div className="flex flex-wrap gap-2">
            {meta.colors.map((color, colorIndex) => {
              const disabled = color.count === 0;
              const slug = color.slug || color.name || '';
              return (
                <button
                  key={`${color.slug || color.name || 'color'}-${colorIndex}`}
                  type="button"
                  onClick={() => !disabled && onToggleColor(slug)}
                  disabled={disabled}
                  className={`w-8 h-8 rounded-full border-2 ${
                    disabled
                      ? 'border-gray-200 opacity-50 cursor-not-allowed'
                      : colors.includes(slug)
                        ? 'border-red-600 scale-110'
                        : 'border-gray-300 hover:border-red-600'
                  }`}
                  style={{ backgroundColor: color.code || '#f5f5f5' }}
                  title={disabled ? 'Нет товаров' : color.name || ''}
                />
              );
            })}
          </div>
        </div>
      )}

      {meta.sizes?.length > 0 && (
        <div className="mb-5">
          <label className="block text-sm font-medium mb-2">Размер</label>
          <div className="flex flex-wrap gap-2">
            {meta.sizes.map((size, sizeIndex) => {
              const disabled = size.count === 0;
              const value = size.value || '';
              return (
                <button
                  key={size.slug || size.value || `size-${sizeIndex}`}
                  type="button"
                  onClick={() => !disabled && onToggleSize(value)}
                  disabled={disabled}
                  className={`px-3 py-2 rounded-lg border text-sm font-medium whitespace-nowrap ${
                    disabled
                      ? 'border-gray-200 text-gray-400 cursor-not-allowed opacity-60'
                      : sizes.includes(value)
                        ? 'border-red-600 bg-red-50 text-red-600'
                        : 'border-gray-300 hover:border-red-600'
                  }`}
                >
                  {size.name || size.value}
                </button>
              );
            })}
          </div>
        </div>
      )}

      {meta.attributes?.map((attr) => (
        <div className="mb-5" key={attr.slug}>
          <label className="block text-sm font-medium mb-2">{attr.name}</label>
          <div className="space-y-2">
            {attr.values.map((val) => {
              const disabled = val.count === 0;
              return (
                <label
                  key={`${attr.slug}-${val.slug}`}
                  className={`flex items-center gap-2 ${disabled ? 'cursor-not-allowed opacity-60' : 'cursor-pointer'}`}
                >
                  <input
                    type="checkbox"
                    checked={(attributes[attr.slug] || []).includes(val.slug)}
                    onChange={() => !disabled && onToggleAttribute(attr.slug, val.slug)}
                    disabled={disabled}
                    className="w-4 h-4 text-red-600 rounded disabled:opacity-50"
                  />
                  <span className="text-sm text-gray-700">{val.name}</span>
                </label>
              );
            })}
          </div>
        </div>
      ))}
    </>
  );
}
