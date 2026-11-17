import { useId, useLayoutEffect, useRef, useState } from 'react';
import type { CSSProperties, PointerEvent, ReactNode } from 'react';
import { cn } from '../../../lib/cn';
import PresencePortal from './PresencePortal';
import { transitionMs, useCloseUnlessLeaving } from './usePresence';
import { useDialog } from './useDialog';

/** center — окно посередине; его выбирает Modal по ширине экрана */
export type SheetSide = 'left' | 'right' | 'bottom' | 'center';

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
  /** Корзина: компактная шапка, отступы 24/16 и шторка не выше 85dvh. */
  appearance?: 'default' | 'cart';
  children: ReactNode;
}

/** Насколько стянуть шторку вниз, чтобы она закрылась, px */
const PULL_TO_CLOSE = 80;

const MOTION = { enterMs: 300, leaveMs: 200 };

/** Модальная панель: слева или шторкой снизу; «Назад» телефона её закрывает (market-docs/45, 54) */
export default function Sheet(props: SheetProps) {
  return (
    <PresencePortal open={props.open} motion={MOTION}>
      {({ shown, leaving }) => <SheetPanel {...props} shown={shown} leaving={leaving} />}
    </PresencePortal>
  );
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
  appearance = 'default',
  children,
}: SheetPanelProps) {
  const panelRef = useRef<HTMLDivElement>(null);
  const titleId = useId();
  const pull = useRef<{ startY: number; dy: number } | null>(null);
  // Полоса прокрутки (scroll-thin) занимает место справа — вычитаем из отступа (market-docs/48)
  const bodyRef = useRef<HTMLDivElement>(null);
  const [gutter, setGutter] = useState(0);
  useLayoutEffect(() => {
    const body = bodyRef.current;
    if (body) setGutter(body.offsetWidth - body.clientWidth);
  }, []);
  const isBottom = side === 'bottom';
  const isCenter = side === 'center';
  const isRight = side === 'right';
  const isCart = appearance === 'cart';
  const padX = isBottom ? 16 : isCenter ? 24 : 32;
  const onCloseRef = useCloseUnlessLeaving(onClose, leaving);

  const trapTab = useDialog(panelRef, onCloseRef);

  const setPull = (dy: number) => {
    const panel = panelRef.current;
    if (!panel) return;
    panel.style.transform = dy > 0 ? `translateY(${dy}px)` : '';
    // Пока тянут пальцем — без перехода, иначе шторка отстаёт от пальца
    panel.style.transitionProperty = dy > 0 ? 'none' : '';
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
    <div
      className={cn(
        'fixed inset-0 z-50',
        isCenter && 'flex items-center justify-center p-4',
        leaving && 'pointer-events-none',
      )}
    >
      <div
        data-testid="sheet-backdrop"
        aria-hidden="true"
        onClick={() => onCloseRef.current()}
        className={cn(
          // Затемнение и размытие «стекло» (макеты 08–09.10)
          'absolute inset-0 bg-ink/40 backdrop-blur transition-opacity motion-reduce:transition-none',
          leaving ? 'ease-in' : 'ease-out',
          shown ? 'opacity-100' : 'opacity-0',
        )}
        style={transitionMs(MOTION, leaving)}
      />
      <div
        ref={panelRef}
        role="dialog"
        aria-modal="true"
        aria-labelledby={titleId}
        data-variant={side}
        tabIndex={-1}
        onKeyDown={trapTab}
        className={cn(
          'flex flex-col bg-surface outline-none',
          'motion-reduce:transition-none',
          isCenter ? 'relative transition-[opacity,transform]' : 'absolute transition-transform',
          leaving ? 'ease-in' : 'ease-out',
          isCenter
            ? cn(
                'max-h-[90vh] w-[534px] max-w-full rounded-card shadow-card',
                shown ? 'scale-100 opacity-100' : 'scale-95 opacity-0',
              )
            : isBottom
              ? cn(
                  'inset-x-0 bottom-0 rounded-t-card',
                  isCart ? 'max-h-[85dvh]' : 'max-h-[90vh]',
                  shown ? 'translate-y-0' : 'translate-y-full',
                )
              : cn(
                  'inset-y-0 max-w-full shadow-drawer',
                  isRight ? 'right-0 w-[570px]' : 'left-0 w-[400px]',
                  shown ? 'translate-x-0' : isRight ? 'translate-x-full' : '-translate-x-full',
                ),
          className,
        )}
        style={transitionMs(MOTION, leaving)}
      >
        {isBottom && (
          <div
            data-testid="sheet-grip"
            aria-hidden="true"
            onPointerDown={startPull}
            onPointerMove={movePull}
            onPointerUp={endPull}
            onPointerCancel={endPull}
            className={cn(
              'flex h-6 shrink-0 cursor-grab touch-none items-center justify-center',
              isCart && 'bg-surface-grey/50 rounded-t-card',
            )}
          >
            <span className="h-1 w-10 rounded-pill bg-surface-border" />
          </div>
        )}
        <div
          className={cn(
            'flex shrink-0 items-center justify-between gap-4',
            isCart
              ? 'bg-surface-grey/50 px-6 py-6 max-vsm:px-4 max-vsm:py-4'
              : isBottom
                ? 'px-4 pb-4'
                : isCenter
                  ? 'px-6 pb-4 pt-6'
                  : 'pb-4 pl-8 pr-6 pt-6',
          )}
        >
          <h2
            id={titleId}
            className={cn(
              'min-w-0 text-ink',
              isCart
                ? 'text-20 font-semibold max-vsm:text-16'
                : isCenter
                  ? 'text-20 font-semibold leading-none'
                  : 'font-bold',
              !isCart && (isBottom ? 'text-20' : !isCenter && 'text-24'),
            )}
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
            isCart
              ? 'flex flex-col pl-6 pr-[max(0px,calc(24px-var(--sheet-gutter)))] pt-6 max-vsm:pl-4 max-vsm:pr-[max(0px,calc(16px-var(--sheet-gutter)))]'
              : headerDivider
                ? 'pt-6'
                : 'pt-2',
            'pb-6',
          )}
          style={
            isCart
              ? ({ '--sheet-gutter': `${gutter}px` } as CSSProperties)
              : { paddingLeft: padX, paddingRight: Math.max(0, padX - gutter) }
          }
        >
          {children}
        </div>
        {footer && (
          <div
            className={cn(
              'shrink-0',
              isCart
                ? 'px-6 pb-[max(24px,env(safe-area-inset-bottom))] max-vsm:px-4 max-vsm:pb-[max(16px,env(safe-area-inset-bottom))]'
                : cn(
                    'border-t border-surface-border',
                    isBottom ? 'px-4 pb-4 pt-4' : isCenter ? 'px-6 pb-6 pt-4' : 'px-8 pb-6 pt-4',
                  ),
            )}
          >
            {footer}
          </div>
        )}
      </div>
    </div>
  );
}
