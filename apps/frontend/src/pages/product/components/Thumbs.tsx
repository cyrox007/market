import { ChevronDown, ChevronRight } from 'lucide-react';
import { cn } from '../../../lib/cn';

/** Картинка превью; у выбранного — затемнение */
export function ThumbImage({
  src,
  active,
  loading = 'lazy',
}: {
  src: string;
  active: boolean;
  loading?: 'eager' | 'lazy';
}) {
  return (
    <>
      <img
        src={src}
        alt=""
        loading={loading}
        decoding="async"
        className="absolute inset-0 size-full object-cover"
      />
      {active && <span aria-hidden="true" className="absolute inset-0 bg-black/[0.32]" />}
    </>
  );
}

/** Серая плашка «ещё превью» поверх края ленты: снизу у галереи, справа в просмотрщике */
export function MoreThumbsButton({
  label,
  direction,
  onClick,
  className,
}: {
  label: string;
  direction: 'down' | 'right';
  onClick: () => void;
  className: string;
}) {
  const Icon = direction === 'down' ? ChevronDown : ChevronRight;
  return (
    <button
      type="button"
      aria-label={label}
      onClick={onClick}
      className={cn(
        'absolute flex items-center justify-center rounded-swatch bg-surface-grey text-ink backdrop-blur-[2px]',
        'transition-colors motion-reduce:transition-none hover:bg-surface-border',
        className,
      )}
    >
      <Icon aria-hidden="true" className="size-6" />
    </button>
  );
}
