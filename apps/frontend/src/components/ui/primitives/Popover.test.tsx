import { useRef, useState } from 'react';
import { act, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import Popover from './Popover';

function Harness({ onClose = () => {} }: { onClose?: () => void }) {
  const anchorRef = useRef<HTMLButtonElement>(null);
  const [open, setOpen] = useState(false);
  const close = () => {
    onClose();
    setOpen(false);
  };
  return (
    <>
      <button ref={anchorRef} type="button" onClick={() => setOpen((v) => !v)}>
        Габариты
      </button>
      <button type="button">Где-то на странице</button>
      <Popover
        open={open}
        onClose={close}
        anchorRef={anchorRef}
        label="Габариты"
        footer={<button type="button">Показать 226 товаров</button>}
      >
        <input aria-label="Ширина от" />
      </Popover>
    </>
  );
}

const openIt = () => {
  const trigger = screen.getByRole('button', { name: 'Габариты' });
  trigger.focus();
  fireEvent.click(trigger);
  return trigger;
};

describe('Popover', () => {
  afterEach(() => vi.restoreAllMocks());

  it('renders nothing until opened', () => {
    render(<Harness />);
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
  });

  it('opens with content and footer and moves focus inside', () => {
    render(<Harness />);
    openIt();
    const dialog = screen.getByRole('dialog', { name: 'Габариты' });
    expect(dialog).toContainElement(screen.getByRole('button', { name: 'Показать 226 товаров' }));
    expect(screen.getByRole('textbox', { name: 'Ширина от' })).toHaveFocus();
  });

  it('closes on Escape and gives focus back to the trigger', () => {
    const onClose = vi.fn();
    render(<Harness onClose={onClose} />);
    const trigger = openIt();

    fireEvent.keyDown(document, { key: 'Escape' });
    expect(onClose).toHaveBeenCalledTimes(1);
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    expect(trigger).toHaveFocus();
  });

  it('closes on a press outside, but not inside or on the trigger', () => {
    const onClose = vi.fn();
    render(<Harness onClose={onClose} />);
    const trigger = openIt();

    fireEvent.pointerDown(screen.getByRole('textbox', { name: 'Ширина от' }));
    fireEvent.pointerDown(trigger);
    expect(onClose).not.toHaveBeenCalled();

    fireEvent.pointerDown(screen.getByRole('button', { name: 'Где-то на странице' }));
    expect(onClose).toHaveBeenCalledTimes(1);
  });

  it('sits under the trigger and does not cross the right edge of the screen', () => {
    vi.spyOn(window, 'innerWidth', 'get').mockReturnValue(400);
    vi.spyOn(HTMLElement.prototype, 'getBoundingClientRect').mockImplementation(function (
      this: HTMLElement,
    ) {
      const rect =
        this.tagName === 'BUTTON'
          ? { left: 300, right: 380, top: 10, bottom: 50, width: 80, height: 40 }
          : { left: 0, right: 200, top: 0, bottom: 100, width: 200, height: 100 };
      return { ...rect, x: rect.left, y: rect.top, toJSON: () => rect } as DOMRect;
    });

    render(<Harness />);
    act(() => {
      openIt();
    });
    const dialog = screen.getByRole('dialog');
    expect(dialog.style.top).toBe('58px');
    expect(dialog.style.left).toBe('184px');
  });
});
