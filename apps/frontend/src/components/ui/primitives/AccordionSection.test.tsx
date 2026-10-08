import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import AccordionSection from './AccordionSection';

describe('AccordionSection', () => {
  it('is open by default and its header controls the body', () => {
    render(
      <AccordionSection title="Цена, ₽">
        <p>поля цены</p>
      </AccordionSection>,
    );

    const header = screen.getByRole('button', { name: 'Цена, ₽' });
    expect(header).toHaveAttribute('aria-expanded', 'true');
    const body = document.getElementById(header.getAttribute('aria-controls')!);
    expect(body).toContainElement(screen.getByText('поля цены'));
    expect(screen.getByText('поля цены')).toBeVisible();
  });

  it('collapses and expands on header click', () => {
    render(
      <AccordionSection title="Габариты, мм">
        <p>поля габаритов</p>
      </AccordionSection>,
    );
    const header = screen.getByRole('button', { name: 'Габариты, мм' });

    fireEvent.click(header);
    expect(header).toHaveAttribute('aria-expanded', 'false');
    expect(screen.getByText('поля габаритов')).not.toBeVisible();

    fireEvent.click(header);
    expect(header).toHaveAttribute('aria-expanded', 'true');
    expect(screen.getByText('поля габаритов')).toBeVisible();
  });

  it('can start collapsed', () => {
    render(
      <AccordionSection title="Производители" defaultOpen={false}>
        <p>список</p>
      </AccordionSection>,
    );
    expect(screen.getByRole('button', { name: 'Производители' })).toHaveAttribute(
      'aria-expanded',
      'false',
    );
    expect(screen.getByText('список')).not.toBeVisible();
  });

  it('can be driven from outside', () => {
    const onOpenChange = vi.fn();
    const { rerender } = render(
      <AccordionSection title="Акция" open={false} onOpenChange={onOpenChange}>
        <p>тумблер</p>
      </AccordionSection>,
    );
    fireEvent.click(screen.getByRole('button', { name: 'Акция' }));
    expect(onOpenChange).toHaveBeenCalledWith(true);
    expect(screen.getByText('тумблер')).not.toBeVisible();

    rerender(
      <AccordionSection title="Акция" open onOpenChange={onOpenChange}>
        <p>тумблер</p>
      </AccordionSection>,
    );
    expect(screen.getByText('тумблер')).toBeVisible();
  });
});
