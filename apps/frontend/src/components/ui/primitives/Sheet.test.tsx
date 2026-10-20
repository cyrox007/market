import { useState } from 'react';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import Sheet from './Sheet';
import { isSheetHistoryEntry } from './sheetHistory';
import type { SheetSide } from './Sheet';

function Harness({
  side = 'left',
  onClose = () => {},
}: {
  side?: SheetSide;
  onClose?: () => void;
}) {
  const [open, setOpen] = useState(false);
  const close = () => {
    onClose();
    setOpen(false);
  };
  return (
    <>
      <button type="button" onClick={() => setOpen(true)}>
        Все фильтры
      </button>
      <Sheet
        open={open}
        onClose={close}
        side={side}
        title="Фильтры"
        headerAction={
          <button type="button" onClick={close}>
            Закрыть
          </button>
        }
        footer={<button type="button">Показать 226 товаров</button>}
      >
        <input aria-label="Цена от" />
      </Sheet>
    </>
  );
}

const openIt = () => {
  const trigger = screen.getByRole('button', { name: 'Все фильтры' });
  trigger.focus();
  fireEvent.click(trigger);
  return trigger;
};

describe('Sheet', () => {
  it('renders nothing until opened', () => {
    render(<Harness />);
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
  });

  it('opens as a modal named by its title, with header action, body and footer', () => {
    render(<Harness />);
    openIt();
    const dialog = screen.getByRole('dialog', { name: 'Фильтры' });
    expect(dialog).toHaveAttribute('aria-modal', 'true');
    expect(dialog).toContainElement(screen.getByRole('button', { name: 'Закрыть' }));
    expect(dialog).toContainElement(screen.getByRole('textbox', { name: 'Цена от' }));
    expect(dialog).toContainElement(screen.getByRole('button', { name: 'Показать 226 товаров' }));
    expect(dialog).toHaveFocus();
  });

  it('closes on Escape and returns focus to the opener', async () => {
    const onClose = vi.fn();
    render(<Harness onClose={onClose} />);
    const trigger = openIt();

    fireEvent.keyDown(document, { key: 'Escape' });
    expect(onClose).toHaveBeenCalledTimes(1);
    // Уезжает с анимацией — убирается после неё
    expect(screen.getByRole('dialog')).toBeInTheDocument();
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
    expect(trigger).toHaveFocus();
  });

  it('closes on a click on the backdrop, not inside the panel', () => {
    const onClose = vi.fn();
    render(<Harness onClose={onClose} />);
    openIt();

    fireEvent.click(screen.getByRole('textbox', { name: 'Цена от' }));
    expect(onClose).not.toHaveBeenCalled();

    fireEvent.click(screen.getByTestId('sheet-backdrop'));
    expect(onClose).toHaveBeenCalledTimes(1);
  });

  it('keeps Tab inside the panel', () => {
    render(<Harness />);
    openIt();
    const first = screen.getByRole('button', { name: 'Закрыть' });
    const last = screen.getByRole('button', { name: 'Показать 226 товаров' });

    last.focus();
    fireEvent.keyDown(last, { key: 'Tab' });
    expect(first).toHaveFocus();

    fireEvent.keyDown(first, { key: 'Tab', shiftKey: true });
    expect(last).toHaveFocus();
  });

  it('locks page scroll while open and restores it after', async () => {
    document.documentElement.style.overflow = 'auto';
    render(<Harness />);
    openIt();
    expect(document.documentElement.style.overflow).toBe('hidden');

    fireEvent.keyDown(document, { key: 'Escape' });
    await waitFor(() => expect(document.documentElement.style.overflow).toBe('auto'));
  });

  it('ignores Escape and backdrop clicks while it slides away', () => {
    const onClose = vi.fn();
    render(<Harness onClose={onClose} />);
    openIt();
    fireEvent.keyDown(document, { key: 'Escape' });
    fireEvent.keyDown(document, { key: 'Escape' });
    fireEvent.click(screen.getByTestId('sheet-backdrop'));
    expect(onClose).toHaveBeenCalledTimes(1);
  });

  it('has a grab handle on the bottom side and closes when pulled down far enough', () => {
    const onClose = vi.fn();
    render(<Harness side="bottom" onClose={onClose} />);
    openIt();
    const grip = screen.getByTestId('sheet-grip');

    fireEvent.pointerDown(grip, { clientY: 100, pointerId: 1 });
    fireEvent.pointerMove(grip, { clientY: 140, pointerId: 1 });
    fireEvent.pointerUp(grip, { clientY: 140, pointerId: 1 });
    expect(onClose).not.toHaveBeenCalled();

    fireEvent.pointerDown(grip, { clientY: 100, pointerId: 2 });
    fireEvent.pointerMove(grip, { clientY: 260, pointerId: 2 });
    fireEvent.pointerUp(grip, { clientY: 260, pointerId: 2 });
    expect(onClose).toHaveBeenCalledTimes(1);
  });

  it('has no handle on the left side', () => {
    render(<Harness side="left" />);
    openIt();
    expect(screen.queryByTestId('sheet-grip')).not.toBeInTheDocument();
  });

  it('кнопка «Назад» телефона закрывает шторку и не уводит со страницы', async () => {
    const onClose = vi.fn();
    const before = window.location.href;
    render(<Harness onClose={onClose} />);
    fireEvent.click(screen.getByRole('button', { name: 'Все фильтры' }));
    await screen.findByRole('dialog');

    window.history.back();

    await waitFor(() => expect(onClose).toHaveBeenCalledTimes(1));
    expect(window.location.href).toBe(before);
  });

  it('закрыли крестиком — запись шторки убрана из истории', async () => {
    render(<Harness />);
    const length = window.history.length;
    fireEvent.click(screen.getByRole('button', { name: 'Все фильтры' }));
    await screen.findByRole('dialog');
    fireEvent.click(screen.getByRole('button', { name: 'Закрыть' }));

    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
    await waitFor(() => expect(isSheetHistoryEntry()).toBe(false));
    // Новая запись осталась бы «вперёд»; назад шагнули ровно на неё
    expect(window.history.length).toBeGreaterThanOrEqual(length);
  });
});
