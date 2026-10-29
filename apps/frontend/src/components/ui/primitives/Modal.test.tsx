import { useState } from 'react';
import { act, fireEvent, render, screen, waitFor } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import Modal from './Modal';

const NARROW = '(max-width: 768px)';
const REDUCED = '(prefers-reduced-motion: reduce)';

let narrow = false;
let reduced = false;
const listeners = new Set<(event: MediaQueryListEvent) => void>();

const setNarrow = (value: boolean) => {
  narrow = value;
  act(() => listeners.forEach((fn) => fn({ matches: value } as MediaQueryListEvent)));
};

beforeEach(() => {
  narrow = false;
  reduced = false;
  window.matchMedia = ((query: string) => ({
    get matches() {
      if (query === NARROW) return narrow;
      if (query === REDUCED) return reduced;
      return false;
    },
    media: query,
    addEventListener: (_: string, fn: (event: MediaQueryListEvent) => void) => {
      if (query === NARROW) listeners.add(fn);
    },
    removeEventListener: (_: string, fn: (event: MediaQueryListEvent) => void) => {
      listeners.delete(fn);
    },
  })) as unknown as typeof window.matchMedia;
});

afterEach(() => listeners.clear());

function Harness({ onClose = () => {} }: { onClose?: () => void }) {
  const [open, setOpen] = useState(false);
  return (
    <>
      <button type="button" onClick={() => setOpen(true)}>
        Купить в 1 клик
      </button>
      <Modal
        open={open}
        onClose={() => {
          onClose();
          setOpen(false);
        }}
        title="Купить в один клик"
      >
        <input aria-label="ФИО" />
        <button type="button">Оформить заказ</button>
      </Modal>
    </>
  );
}

const openIt = () => {
  const trigger = screen.getByRole('button', { name: 'Купить в 1 клик' });
  trigger.focus();
  fireEvent.click(trigger);
  return trigger;
};

describe('Modal', () => {
  it('ничего не показывает, пока не открыто', () => {
    render(<Harness />);
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
  });

  it('шире 768 — окно по центру с заголовком, без ручки шторки', () => {
    render(<Harness />);
    openIt();
    const dialog = screen.getByRole('dialog', { name: 'Купить в один клик' });
    expect(dialog).toHaveAttribute('aria-modal', 'true');
    expect(dialog).toContainElement(screen.getByRole('textbox', { name: 'ФИО' }));
    expect(dialog).toHaveAttribute('data-variant', 'center');
    expect(screen.queryByTestId('sheet-grip')).not.toBeInTheDocument();
  });

  it('768 и уже — шторка снизу', () => {
    narrow = true;
    render(<Harness />);
    openIt();
    expect(screen.getByRole('dialog')).toHaveAttribute('data-variant', 'bottom');
    expect(screen.getByTestId('sheet-grip')).toBeInTheDocument();
  });

  it('смена ширины при открытом окне переключает вид', () => {
    render(<Harness />);
    openIt();
    setNarrow(true);
    expect(screen.getByRole('dialog')).toHaveAttribute('data-variant', 'bottom');
    setNarrow(false);
    expect(screen.getByRole('dialog')).toHaveAttribute('data-variant', 'center');
  });

  it('крестик закрывает и возвращает фокус на кнопку, открывшую окно', async () => {
    const onClose = vi.fn();
    render(<Harness onClose={onClose} />);
    const trigger = openIt();
    fireEvent.click(screen.getByRole('button', { name: 'Закрыть' }));
    expect(onClose).toHaveBeenCalledTimes(1);
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
    expect(trigger).toHaveFocus();
  });

  it('закрывается по Esc и по нажатию на фон, но не внутри окна', () => {
    const onClose = vi.fn();
    render(<Harness onClose={onClose} />);
    openIt();
    fireEvent.click(screen.getByRole('textbox', { name: 'ФИО' }));
    expect(onClose).not.toHaveBeenCalled();
    fireEvent.click(screen.getByTestId('sheet-backdrop'));
    expect(onClose).toHaveBeenCalledTimes(1);

    fireEvent.click(screen.getByRole('button', { name: 'Купить в 1 клик' }));
    fireEvent.keyDown(document, { key: 'Escape' });
    expect(onClose).toHaveBeenCalledTimes(2);
  });

  it('«Назад» закрывает окно и не уводит со страницы', async () => {
    const onClose = vi.fn();
    const before = window.location.href;
    render(<Harness onClose={onClose} />);
    openIt();
    await screen.findByRole('dialog');
    window.history.back();
    await waitFor(() => expect(onClose).toHaveBeenCalledTimes(1));
    expect(window.location.href).toBe(before);
  });

  it('Tab не выходит из окна', () => {
    render(<Harness />);
    openIt();
    const first = screen.getByRole('button', { name: 'Закрыть' });
    const last = screen.getByRole('button', { name: 'Оформить заказ' });
    last.focus();
    fireEvent.keyDown(last, { key: 'Tab' });
    expect(first).toHaveFocus();
    fireEvent.keyDown(first, { key: 'Tab', shiftKey: true });
    expect(last).toHaveFocus();
  });

  it('с «уменьшить движение» убирается сразу, без анимации ухода', () => {
    reduced = true;
    render(<Harness />);
    openIt();
    fireEvent.keyDown(document, { key: 'Escape' });
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
  });
});
