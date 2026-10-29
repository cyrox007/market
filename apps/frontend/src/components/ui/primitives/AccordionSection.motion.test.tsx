import { act, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import AccordionSection from './AccordionSection';

describe('AccordionSection: обрезка при раскрытии', () => {
  afterEach(() => vi.useRealTimers());

  it('снимает обрезку раскрытого содержимого, даже если transitionend не пришёл', () => {
    vi.useFakeTimers();
    render(
      <AccordionSection title="Материал" defaultOpen={false}>
        <p>чекбоксы материала</p>
      </AccordionSection>,
    );

    fireEvent.click(screen.getByRole('button', { name: 'Материал' }));
    act(() => {
      vi.advanceTimersByTime(1000);
    });

    const clip = screen.getByText('чекбоксы материала').parentElement!.parentElement!;
    expect(clip).not.toHaveClass('overflow-hidden');
  });

  it('с «уменьшить движение» снимает обрезку сразу', () => {
    vi.stubGlobal('matchMedia', (query: string) => ({ matches: query.includes('reduce') }));
    render(
      <AccordionSection title="Цвет" defaultOpen={false}>
        <p>чекбоксы цвета</p>
      </AccordionSection>,
    );

    fireEvent.click(screen.getByRole('button', { name: 'Цвет' }));

    const clip = screen.getByText('чекбоксы цвета').parentElement!.parentElement!;
    expect(clip).not.toHaveClass('overflow-hidden');
    vi.unstubAllGlobals();
  });

  it('пока раскрывается — обрезает', () => {
    render(
      <AccordionSection title="Размер" defaultOpen={false}>
        <p>чекбоксы размера</p>
      </AccordionSection>,
    );

    fireEvent.click(screen.getByRole('button', { name: 'Размер' }));

    const clip = screen.getByText('чекбоксы размера').parentElement!.parentElement!;
    expect(clip).toHaveClass('overflow-hidden');
  });
});
