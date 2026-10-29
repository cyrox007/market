import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { CarouselArrow } from './Carousel';

describe('стрелка карусели', () => {
  it('видимая — обычная кнопка', () => {
    render(
      <CarouselArrow side="next" hidden={false} label="Вперёд" onClick={vi.fn()} className="" />,
    );
    expect(screen.getByRole('button', { name: 'Вперёд' })).not.toHaveAttribute('tabindex', '-1');
  });

  it('скрытая у края — не ловит Tab и не видна читалке', () => {
    render(<CarouselArrow side="prev" hidden label="Назад" onClick={vi.fn()} className="" />);
    expect(screen.queryByRole('button', { name: 'Назад' })).not.toBeInTheDocument();
    const button = screen.getByRole('button', { hidden: true });
    expect(button).toHaveAttribute('tabindex', '-1');
    expect(button).toHaveAttribute('aria-hidden', 'true');
  });
});
