import { ChevronLeft, ChevronRight } from 'lucide-react';
import { IconButton } from '../primitives';
import { cn } from '../../../lib/cn';

/** Белое затухание у края карусели; исчезает, когда в ту сторону листать некуда */
export function CarouselFade({
  side,
  hidden,
  width,
  className,
  veil = false,
}: {
  side: 'left' | 'right';
  hidden: boolean;
  width?: number;
  className?: string;
  /** Вуаль от 58 % до сплошного белого (Figma 919:75816), а не затухание до прозрачного */
  veil?: boolean;
}) {
  return (
    <div
      aria-hidden="true"
      style={width ? { width } : undefined}
      className={cn(
        // z-10 обязателен: Swiper объявляет себе z-index 1 и иначе перекрывает затухание
        // За пределы: сверху 1, слева 1, справа 2 — иначе по краю остаётся волосок карточки
        'pointer-events-none absolute -top-px bottom-0 z-10 w-24 transition-opacity duration-slow',
        side === 'left' ? '-left-px bg-gradient-to-r' : '-right-0.5 bg-gradient-to-l',
        veil ? 'from-surface to-surface/[0.58]' : 'from-surface via-surface/80 to-transparent',
        hidden && 'opacity-0',
        className,
      )}
    />
  );
}

/** Круглая белая стрелка; положение по вертикали и от края задаёт вызывающий */
export function CarouselArrow({
  side,
  hidden,
  label,
  onClick,
  className,
  glass = false,
}: {
  side: 'prev' | 'next';
  hidden: boolean;
  label: string;
  onClick: () => void;
  className: string;
  /** Полупрозрачная 48 с иконкой 24 (Figma 919:75821) вместо белой 40 с главной */
  glass?: boolean;
}) {
  const Icon = side === 'prev' ? ChevronLeft : ChevronRight;
  return (
    <IconButton
      variant={glass ? 'glass' : 'white'}
      size={glass ? 'lg' : 'md'}
      elevated={!glass}
      label={label}
      onClick={onClick}
      className={cn(
        'absolute z-10 -translate-y-1/2',
        glass && 'shadow-[0_2px_10px_0_rgba(20,20,20,0.1)]',
        className,
        hidden && 'pointer-events-none opacity-0',
      )}
    >
      <Icon className={glass ? 'size-6' : 'size-5'} strokeWidth={glass ? 1.5 : 2} />
    </IconButton>
  );
}
