import { CircleDashed } from 'lucide-react';
import type { LucideIcon, LucideProps } from 'lucide-react';
import { resolveIcon } from './registry';

/**
 * Иконка может быть задана двумя способами:
 *  - готовым компонентом Lucide, если она известна на этапе сборки;
 *  - строкой, если имя приходит с бэкенда.
 */
export type IconSource = LucideIcon | string;

/**
 * Чем рисуется нераспознанное имя.
 *
 * Пунктирный круг выбран намеренно: он держит размер и не притворяется
 * осмысленной иконкой, поэтому расхождение видно, но вёрстка не рассыпается.
 */
const UNKNOWN_ICON: LucideIcon = CircleDashed;

export interface IconProps extends Omit<LucideProps, 'ref' | 'name'> {
  name?: IconSource | null;
  /** Чем заменить нераспознанное имя вместо стандартной заглушки. */
  fallback?: LucideIcon;
}

/**
 * Рисует иконку по имени или по компоненту.
 *
 * Размер задаётся классами `w-*`/`h-*`, а не пропом `size`: Tailwind считает
 * отступы в rem, а корневой font-size в проекте адаптивный (14px, 15px от 1024,
 * 16px от 1440). Так иконки масштабируются вместе с текстом, как это было
 * со шрифтовыми иконками Remixicon.
 *
 * Иконки здесь декоративные и дублируют соседний текст, поэтому скрыты
 * от скринридеров. Если иконка несёт смысл сама по себе, передайте aria-label.
 */
export default function Icon({ name, fallback, className, ...rest }: IconProps) {
  // Пустое значение — законный случай: поле иконки в API необязательное.
  // Заглушку здесь ставить нельзя, иначе она полезет во все места,
  // где иконку просто не задавали.
  if (!name) return null;

  if (typeof name !== 'string') {
    const Component = name;
    return <Component className={className} aria-hidden {...rest} />;
  }

  const resolved = resolveIcon(name);

  // Имя есть, но словарь его не знает: опечатка в админке или иконка,
  // которой нет в наборе. Рисуем заглушку, а не пустоту — иначе расхождение
  // можно не заметить месяцами. Поле иконки в Filament — свободный ввод,
  // так что случай не гипотетический.
  if (!resolved) {
    const Fallback = fallback ?? UNKNOWN_ICON;
    return <Fallback className={className} aria-hidden {...rest} />;
  }

  const { icon: Component, filled } = resolved;
  return (
    <Component
      className={className}
      fill={filled ? 'currentColor' : 'none'}
      aria-hidden
      {...rest}
    />
  );
}
