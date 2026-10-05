import { cn } from '../../../lib/cn';

export interface ColorSwatch {
  /** CSS-цвет: hex, rgb, а для текстур — градиент или url() */
  value: string;
  title?: string;
}

interface ColorSwatchesProps {
  colors: ColorSwatch[];
  /** Сколько «кубиков» помещается, считая счётчик «+N». Не поместились все цвета —
   * показываем `limit - 1` образцов и счётчик последним кубиком */
  limit?: number;
  /** То же на мобильной ширине (до 769). По умолчанию как `limit` */
  mobileLimit?: number;
  className?: string;
}

/** Сколько образцов видно при данном числе кубиков */
const visibleCount = (total: number, limit: number) => (total > limit ? limit - 1 : total);

/**
 * Образцы цвета с карточки товара. Квадрат 24, радиус 4, промежуток 8 (макет 07.09.2026).
 *
 * Сворачивание считается в «кубиках», счётчик «+N» — тоже кубик: при `limit = 4` и восьми
 * цветах видно три образца и «+5». Отдельный лимит для мобильной ширины переключается
 * классами `max-sm:`, без JS — нет мигания и рассинхрона с сервером. Правила карточки —
 * market-docs/09, 38.
 */
export default function ColorSwatches({ colors, limit = 4, mobileLimit = limit, className }: ColorSwatchesProps) {
  const desktopVisible = visibleCount(colors.length, limit);
  const mobileVisible = visibleCount(colors.length, mobileLimit);
  const counter = 'h-6 min-w-6 shrink-0 items-center justify-center rounded-swatch bg-surface-grey px-1 text-8 text-ink-secondary';

  return (
    <div
      className={cn('inline-flex items-center gap-2', className)}
      role="group"
      aria-label={`Доступных цветов: ${colors.length}`}
    >
      {colors.map((color, index) => {
        if (index >= desktopVisible && index >= mobileVisible) return null;
        return (
          <span
            key={`${color.value}-${index}`}
            title={color.title}
            className={cn(
              'size-6 shrink-0 rounded-swatch border border-surface-border',
              index >= desktopVisible && 'hidden max-sm:block',
              index >= mobileVisible && 'max-sm:hidden',
            )}
            style={{ background: color.value }}
          />
        );
      })}

      {desktopVisible < colors.length && (
        <span aria-hidden="true" className={cn(counter, 'inline-flex max-sm:hidden')}>
          +{colors.length - desktopVisible}
        </span>
      )}
      {mobileVisible < colors.length && (
        <span aria-hidden="true" className={cn(counter, 'hidden max-sm:inline-flex')}>
          +{colors.length - mobileVisible}
        </span>
      )}
    </div>
  );
}
