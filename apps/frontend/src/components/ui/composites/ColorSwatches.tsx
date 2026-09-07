import { cn } from '../../../lib/cn';

export interface ColorSwatch {
  /** CSS-цвет: hex, rgb, а для текстур — градиент или url() */
  value: string;
  title?: string;
}

interface ColorSwatchesProps {
  colors: ColorSwatch[];
  /**
   * Сколько образцов помещается без сворачивания. Пятый и далее заменяются
   * счётчиком, и тогда видно только три образца плюс он сам.
   */
  limit?: number;
  className?: string;
}

/**
 * Образцы цвета с карточки товара.
 *
 * Замерено с макета 07.09.2026: квадрат 24, радиус 4, промежуток 8.
 *
 * Правило сворачивания со слов владельца: если цветов больше четырёх, показываем
 * **три** образца, а четвёртым местом — серый прямоугольник со счётчиком «+8».
 * То есть счётчик занимает место четвёртого образца, а не добавляется к четырём.
 * При четырёх и менее показываем все.
 *
 * Размер шрифта в счётчике замерен: 8. В шкале его не было, добавлен в конфиг.
 *
 * Ширина счётчика ждёт ответа дизайнера. Пока он тянется по содержимому от 24.
 * Практически это ничего не меняет: по ожиданию владельца значение не превысит
 * «+9», а при шрифте 8 в 24 пикселя влезает и двузначное — проверено замером.
 */
export default function ColorSwatches({ colors, limit = 4, className }: ColorSwatchesProps) {
  const overflows = colors.length > limit;
  const visible = overflows ? colors.slice(0, limit - 1) : colors;
  const hidden = colors.length - visible.length;

  return (
    <div
      className={cn('inline-flex items-center gap-2', className)}
      role="group"
      aria-label={`Доступных цветов: ${colors.length}`}
    >
      {visible.map((color, index) => (
        <span
          key={`${color.value}-${index}`}
          title={color.title}
          className="size-6 shrink-0 rounded-swatch border border-surface-border"
          style={{ background: color.value }}
        />
      ))}

      {overflows && (
        <span className="inline-flex h-6 min-w-6 shrink-0 items-center justify-center rounded-swatch bg-surface-grey px-1 text-8 text-ink-secondary">
          +{hidden}
        </span>
      )}
    </div>
  );
}
