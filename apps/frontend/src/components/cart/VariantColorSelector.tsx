import type { ColorOption } from '../../lib/api';

interface VariantColorSelectorProps {
  colors: ColorOption[];
  selectedColor: { slug?: string; name?: string } | null;
  selectedSize: { value?: string; slug?: string } | null;
  variantInfo?: any;
  isDisabled?: boolean;
  onSelect: (color: ColorOption, isDisabledClick?: boolean) => void | Promise<void>;
  isColorAvailable: (color: ColorOption) => boolean;
}

export default function VariantColorSelector({
  colors,
  selectedColor,
  selectedSize,
  variantInfo,
  isDisabled = false,
  onSelect,
  isColorAvailable,
}: VariantColorSelectorProps) {
  if (colors.length === 0) return null;

  return (
    <div>
      <label className="text-sm font-medium text-gray-700 mb-1 block">
        Цвет: <span className="text-gray-600 font-normal">{selectedColor?.name || 'Выберите цвет'}</span>
      </label>
      <div className="flex flex-wrap gap-2">
        {colors.map((color, index) => {
          const colorKey = color.slug || color.name || color.id || `color-${index}`;
          const selectedKey = selectedColor?.slug || selectedColor?.name;
          const isSelected = colorKey === selectedKey && selectedColor !== null;
          const isColorDisabled = !isColorAvailable(color);

          return (
            <button
              key={colorKey}
              onClick={() => {
                if (isDisabled && !isColorDisabled) return;
                if (isColorDisabled) {
                  // Клик на недоступный цвет - выбираем доступный размер
                  onSelect(color, true);
                } else {
                  // Клик на доступный цвет
                  onSelect(color, false);
                }
              }}
              disabled={isDisabled && !isColorDisabled}
              className={`w-10 h-10 rounded-lg border-2 transition-all ${
                isSelected
                  ? 'border-red-600 scale-110 ring-2 ring-red-200 cursor-default'
                  : isColorDisabled
                  ? 'border-gray-200 cursor-pointer hover:opacity-50'
                  : 'border-gray-300 hover:border-red-600 cursor-pointer'
              } ${isDisabled && !isColorDisabled ? 'cursor-wait' : ''}`}
              style={{
                backgroundColor: color.code || '#f5f5f5',
                opacity: isColorDisabled ? 0.3 : (isDisabled && !isColorDisabled ? 0.5 : 1),
              }}
              title={isColorDisabled ? `Нажмите, чтобы выбрать доступный размер для цвета "${color.name}"` : color.name || ''}
            />
          );
        })}
      </div>
    </div>
  );
}
