import type { SizeOption } from '../../lib/api';

interface VariantSizeSelectorProps {
  sizes: SizeOption[];
  selectedSize: { slug?: string; value?: string; name?: string } | null;
  selectedColor: { slug?: string; name?: string } | null;
  variantInfo?: any;
  isDisabled?: boolean;
  onSelect: (size: SizeOption, isDisabledClick?: boolean) => void | Promise<void>;
  isSizeAvailable: (size: SizeOption) => boolean;
}

export default function VariantSizeSelector({
  sizes,
  selectedSize,
  selectedColor,
  variantInfo,
  isDisabled = false,
  onSelect,
  isSizeAvailable,
}: VariantSizeSelectorProps) {
  if (sizes.length === 0) return null;

  return (
    <div>
      <label className="text-sm font-medium text-gray-700 mb-1 block">
        Размер:{' '}
        <span className="text-gray-600 font-normal">
          {selectedSize?.name || selectedSize?.value || 'Выберите размер'}
        </span>
      </label>
      <div className="flex flex-wrap gap-2">
        {sizes.map((size, index) => {
          const sizeKey = size.slug || size.value || size.id || `size-${index}`;
          const selectedKey = selectedSize?.slug || selectedSize?.value;
          const isSelected = sizeKey === selectedKey && selectedSize !== null;
          const isSizeDisabled = !isSizeAvailable(size);

          return (
            <button
              key={sizeKey}
              onClick={() => {
                if (isDisabled && !isSizeDisabled) return;
                if (isSizeDisabled) {
                  // Клик на недоступный размер - выбираем доступный цвет
                  onSelect(size, true);
                } else {
                  // Клик на доступный размер
                  onSelect(size, false);
                }
              }}
              disabled={isDisabled && !isSizeDisabled}
              className={`px-4 py-2.5 rounded-lg border-2 text-sm font-medium whitespace-nowrap transition-all ${
                isSelected
                  ? 'border-red-600 bg-red-50 text-red-600 font-semibold cursor-default'
                  : isSizeDisabled
                    ? 'border-gray-200 text-gray-400 cursor-pointer hover:opacity-50'
                    : 'border-gray-300 hover:border-red-600 cursor-pointer'
              } ${isDisabled && !isSizeDisabled ? 'cursor-wait' : ''}`}
              style={{
                opacity: isSizeDisabled ? 0.3 : isDisabled && !isSizeDisabled ? 0.5 : 1,
              }}
              title={
                isSizeDisabled
                  ? `Нажмите, чтобы выбрать доступный цвет для размера "${size.name || size.value}"`
                  : size.name || size.value || ''
              }
            >
              {size.name || size.value || size.slug}
            </button>
          );
        })}
      </div>
    </div>
  );
}
