import { CircleDashed } from 'lucide-react';
import type { LucideIcon, LucideProps } from 'lucide-react';
import { resolveIcon } from './registry';

/**
 * Иконка может быть задана двумя способами:
 *  - готовым компонентом Lucide, если она известна на этапе сборки;
 *  - каноническим Lucide-slug, если имя приходит с backend.
 */
export type IconSource = LucideIcon | string;

/**
 * Чем рисуется нераспознанное имя.
 *
 * Пунктирный круг держит размер и явно показывает расхождение контракта,
 * если backend неожиданно вернул slug вне согласованного реестра.
 */
const UNKNOWN_ICON: LucideIcon = CircleDashed;

export interface IconProps extends Omit<LucideProps, 'ref' | 'name'> {
  name?: IconSource | null;
  /** Чем заменить нераспознанный slug вместо стандартной заглушки. */
  fallback?: LucideIcon;
}

/**
 * Рисует иконку по каноническому Lucide-slug или по готовому компоненту.
 *
 * Размер задаётся классами `w-*`/`h-*`, а не пропом `size`: Tailwind считает
 * отступы в rem, а корневой font-size в проекте адаптивный.
 */
export default function Icon({ name, fallback, className, ...rest }: IconProps) {
  if (!name) return null;

  if (typeof name !== 'string') {
    const Component = name;
    return <Component className={className} aria-hidden {...rest} />;
  }

  const resolved = resolveIcon(name);

  if (!resolved) {
    const Fallback = fallback ?? UNKNOWN_ICON;
    return <Fallback className={className} aria-hidden {...rest} />;
  }

  const { icon: Component } = resolved;
  return <Component className={className} aria-hidden {...rest} />;
}
