import { useEffect, useId, useLayoutEffect, useRef, useState } from 'react';
import type { KeyboardEvent, PointerEvent, ReactNode } from 'react';
import { createPortal } from 'react-dom';
import { focusableIn } from './focusable';
import { cn } from '../../../lib/cn';
import { usePresence } from './usePresence';

export type SheetSide = 'left' | 'bottom';

interface SheetProps {
  open: boolean;
  /** Esc, клик по затемнению, стягивание вниз. Состояние open меняет вызывающий */
  onClose: () => void;
  side: SheetSide;
  title: ReactNode;
  /** Справа в заголовке: крестик или «Сбросить» — решает вызывающий */
  headerAction?: ReactNode;
  /** Линия под заголовком — у шторки фильтров на телефоне (макет Mobile 375) */
  headerDivider?: boolean;
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
/** Уход, мс — как duration-200 у классов ниже; появление — duration-300 (market-docs/48) */
const LEAVE_MS = 200;

export default function Sheet(props: SheetProps) {
  const { rendered, shown, leaving } = usePresence(props.open, LEAVE_MS);
  if (!rendered) return null;
  return createPortal(<SheetPanel {...props} shown={shown} leaving={leaving} />, document.body);
}

interface SheetPanelProps extends SheetProps {
  shown: boolean;
  leaving: boolean;
}

function SheetPanel({
  shown,
  leaving,
  onClose,
  side,
  title,
  headerAction,
  headerDivider = false,
  footer,
  className,
  children,
}: SheetPanelProps) {
  const panelRef = useRef<HTMLDivElement>(null);
  const titleId = useId();
  const pull = useRef<{ startY: number; dy: number } | null>(null);
  // Место под полосу прокрутки занято всегда (scroll-thin); вычитаем его из правого отступа,
  // чтобы край содержимого совпал с футером. На Windows ~10 px, на Mac с наложенной полосой — 0
  const bodyRef = useRef<HTMLDivElement>(null);
  const [gutter, setGutter] = useState(0);
  useLayoutEffect(() => {
    const body = bodyRef.current;
    if (body) setGutter(body.offsetWidth - body.clientWidth);
  }, []);
  const isBottom = side === 'bottom';

  // Пока уезжает, повторное закрытие не зовём
  const leavingRef = useRef(leaving);
  const onCloseRef = useRef(() => {
    if (!leavingRef.current) onClose();
  });
  useLayoutEffect(() => {
    leavingRef.current = leaving;
    onCloseRef.current = () => {
      if (!leavingRef.current) onClose();
    };
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
    const panel = panelRef.current;
    if (!panel) return;
    panel.style.transform = dy > 0 ? `translateY(${dy}px)` : '';
    // Пока тянут пальцем — без перехода, иначе шторка отстаёт от пальца
    panel.style.transition = dy > 0 ? 'none' : '';
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
    setPull(0);
    if (dy >= PULL_TO_CLOSE) onCloseRef.current();
  };

  return (
    <div className={cn('fixed inset-0 z-50', leaving && 'pointer-events-none')}>
      <div
        data-testid="sheet-backdrop"
        aria-hidden="true"
        onClick={() => onCloseRef.current()}
        className={cn(
          // Затемнение и размытие «стекло» (макеты 08–09.10)
          'absolute inset-0 bg-ink/40 backdrop-blur transition-opacity motion-reduce:transition-none',
          leaving ? 'duration-200 ease-in' : 'duration-300 ease-out',
          shown ? 'opacity-100' : 'opacity-0',
        )}
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
          'transition-transform motion-reduce:transition-none',
          leaving ? 'duration-200 ease-in' : 'duration-300 ease-out',
          isBottom
            ? cn(
                'inset-x-0 bottom-0 max-h-[90vh] rounded-t-card',
                shown ? 'translate-y-0' : 'translate-y-full',
              )
            : cn(
                'inset-y-0 left-0 w-[400px] max-w-full shadow-drawer',
                shown ? 'translate-x-0' : '-translate-x-full',
              ),
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
        {headerDivider && (
          <hr className={cn('shrink-0 border-surface-border', isBottom ? 'mx-4' : 'mx-8')} />
        )}
        <div
          ref={bodyRef}
          className={cn(
            // overflow-x-hidden: бегунок ползунка в крайнем положении выходит на полкружка за край
            'scroll-thin min-h-0 flex-1 overflow-y-auto overflow-x-hidden overscroll-contain',
            headerDivider ? 'pt-6' : 'pt-2',
            isBottom ? 'pb-6 pl-4' : 'pb-6 pl-8',
          )}
          style={{ paddingRight: Math.max(0, (isBottom ? 16 : 32) - gutter) }}
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
