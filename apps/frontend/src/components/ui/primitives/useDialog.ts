import { useEffect } from 'react';
import type { KeyboardEvent, MutableRefObject, RefObject } from 'react';
import { focusableIn } from './focusable';
import { HISTORY_KEY, isSheetHistoryEntry } from './sheetHistory';

let openDialogs = 0;

/**
 * Поведение модального окна (Sheet, просмотр фото): фокус внутрь и обратно на кнопку,
 * прокрутка страницы заперта, Esc и «Назад» телефона закрывают. Возвращает ловушку Tab.
 */
export function useDialog(
  panelRef: RefObject<HTMLElement | null>,
  onCloseRef: MutableRefObject<() => unknown>,
) {
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
  }, [panelRef, onCloseRef]);

  // Своя запись в истории: «Назад» снимает её и закрывает окно, а не уводит со страницы
  useEffect(() => {
    openDialogs += 1;
    if (!isSheetHistoryEntry()) {
      window.history.pushState({ ...window.history.state, [HISTORY_KEY]: true }, '');
    }
    const handlePop = () => onCloseRef.current();
    window.addEventListener('popstate', handlePop);
    return () => {
      openDialogs -= 1;
      window.removeEventListener('popstate', handlePop);
      // Отложено: при повторном монтировании (StrictMode) запись остаётся за новым окном
      setTimeout(() => {
        if (openDialogs === 0 && isSheetHistoryEntry()) window.history.back();
      });
    };
  }, [onCloseRef]);

  return (event: KeyboardEvent<HTMLElement>) => {
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
}
