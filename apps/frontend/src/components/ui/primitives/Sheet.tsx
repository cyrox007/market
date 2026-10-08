import { useEffect, useId, useLayoutEffect, useRef } from 'react';
import type { KeyboardEvent, PointerEvent, ReactNode } from 'react';
import { createPortal } from 'react-dom';
import { focusableIn } from './focusable';
import { cn } from '../../../lib/cn';

export type SheetSide = 'left' | 'bottom';

interface SheetProps {
  open: boolean;
  /** Esc, клик по затемнению, стягивание вниз. Состояние open меняет вызывающий */
  onClose: () => void;
  side: SheetSide;
  title: ReactNode;
  /** Справа в заголовке: крестик или «Сбросить» — решает вызывающий */
  headerAction?: ReactNode;
  /** Прилипает к низу, тело прокручивается над ним */
  footer?: ReactNode;
  className?: string;
  children: ReactNode;
}

/** Насколько стянуть шторку вниз, чтобы она закрылась, px */
const PULL_TO_CLOSE = 80;

/**
 * Модальная панель: слева («Все фильтры» на десктопе) или шторкой снизу (телефон).
 * Кнопку «Назад» телефона не перехватывает — это решает страница. Замеры — market-docs/45.
 */
export default function Sheet(props: SheetProps) {
  if (!props.open) return null;
  return createPortal(<SheetPanel {...props} />, document.body);
}

function SheetPanel({
  onClose,
  side,
  title,
  headerAction,
  footer,
  className,
  children,
}: SheetProps) {
  const panelRef = useRef<HTMLDivElement>(null);
  const titleId = useId();
  const pull = useRef<{ startY: number; dy: number } | null>(null);
  const isBottom = side === 'bottom';

  const onCloseRef = useRef(onClose);
  useLayoutEffect(() => {
    onCloseRef.current = onClose;
  });

  useEffect(() => {
    const opener = document.activeElement as HTMLElement | null;
    panelRef.current?.focus({ preventScroll: true });

    // overflow на <html>, не position: fixed на body — тот увёл бы липкую шапку за экран
    const root = document.documentElement;
    const previousOverflow = root.style.overflow;
    root.style.overflow = 'hidden';

    const handleKey = (event: globalThis.KeyboardEvent) => {
      if (event.key === 'Escape') onCloseRef.current();
    };
    document.addEventListener('keydown', handleKey);

    return () => {
      document.removeEventListener('keydown', handleKey);
      root.style.overflow = previousOverflow;
      opener?.focus({ preventScroll: true });
    };
  }, []);

  const trapTab = (event: KeyboardEvent<HTMLDivElement>) => {
    if (event.key !== 'Tab' || !panelRef.current) return;
    const items = focusableIn(panelRef.current);
    if (items.length === 0) {
      event.preventDefault();
      return;
    }
    const first = items[0];
    const last = items[items.length - 1];
    const active = document.activeElement;
    if (event.shiftKey && (active === first || active === panelRef.current)) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && (active === last || active === panelRef.current)) {
      event.preventDefault();
      first.focus();
    }
  };

  const setPull = (dy: number) => {
    if (panelRef.current) panelRef.current.style.transform = dy > 0 ? `translateY(${dy}px)` : '';
  };

  const startPull = (event: PointerEvent<HTMLDivElement>) => {
    event.currentTarget.setPointerCapture?.(event.pointerId);
    pull.current = { startY: event.clientY, dy: 0 };
  };

  const movePull = (event: PointerEvent<HTMLDivElement>) => {
    if (!pull.current) return;
    pull.current.dy = Math.max(0, event.clientY - pull.current.startY);
    setPull(pull.current.dy);
  };

  const endPull = () => {
    if (!pull.current) return;
    const { dy } = pull.current;
    pull.current = null;
    if (dy >= PULL_TO_CLOSE) onCloseRef.current();
    else setPull(0);
  };

  return (
    <div className="fixed inset-0 z-50">
      <div
        data-testid="sheet-backdrop"
        aria-hidden="true"
        onClick={() => onCloseRef.current()}
        className="absolute inset-0 bg-ink/40"
      />
      <div
        ref={panelRef}
        role="dialog"
        aria-modal="true"
        aria-labelledby={titleId}
        tabIndex={-1}
        onKeyDown={trapTab}
        className={cn(
          'absolute flex flex-col bg-surface outline-none',
          isBottom
            ? 'inset-x-0 bottom-0 max-h-[90vh] rounded-t-card'
            : 'inset-y-0 left-0 w-[400px] max-w-full shadow-drawer',
          className,
        )}
      >
        {isBottom && (
          <div
            data-testid="sheet-grip"
            aria-hidden="true"
            onPointerDown={startPull}
            onPointerMove={movePull}
            onPointerUp={endPull}
            onPointerCancel={endPull}
            className="flex h-6 shrink-0 cursor-grab touch-none items-center justify-center"
          >
            <span className="h-1 w-10 rounded-pill bg-surface-border" />
          </div>
        )}
        <div
          className={cn(
            'flex shrink-0 items-center justify-between gap-4',
            isBottom ? 'px-4 pb-4' : 'pb-4 pl-8 pr-6 pt-6',
          )}
        >
          <h2
            id={titleId}
            className={cn('min-w-0 font-bold text-ink', isBottom ? 'text-20' : 'text-24')}
          >
            {title}
          </h2>
          {headerAction}
        </div>
        <div
          className={cn(
            'min-h-0 flex-1 overflow-y-auto overscroll-contain',
            isBottom ? 'px-4 pb-6 pt-2' : 'px-8 pb-6 pt-2',
          )}
        >
          {children}
        </div>
        {footer && (
          <div
            className={cn(
              'shrink-0 border-t border-surface-border',
              isBottom ? 'px-4 pb-4 pt-4' : 'px-8 pb-6 pt-4',
            )}
          >
            {footer}
          </div>
        )}
      </div>
    </div>
  );
}
