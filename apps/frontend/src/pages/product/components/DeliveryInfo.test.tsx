import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import DeliveryInfo from './DeliveryInfo';

describe('сроки под вариантами', () => {
  it('доставка и самовывоз с городом', () => {
    render(<DeliveryInfo city="Липецк" delivery="3–5 дней" pickup="Сегодня" />);
    expect(screen.getByText('Доставка:').parentElement).toHaveTextContent(
      'Доставка: г. Липецк — 3–5 дней',
    );
    expect(screen.getByText('Самовывоз:').parentElement).toHaveTextContent(
      'Самовывоз: г. Липецк — Сегодня',
    );
  });

  it('самовывоза нет — только доставка', () => {
    render(<DeliveryInfo city="Липецк" delivery="2 дня" pickup={null} />);
    expect(screen.getByText('Доставка:')).toBeInTheDocument();
    expect(screen.queryByText('Самовывоз:')).not.toBeInTheDocument();
  });

  it('расчёта нет — блока нет', () => {
    const { container } = render(<DeliveryInfo city="Липецк" delivery={null} pickup={null} />);
    expect(container).toBeEmptyDOMElement();
  });
});
