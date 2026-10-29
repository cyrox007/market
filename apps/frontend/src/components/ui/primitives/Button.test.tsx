import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import Button from './Button';

describe('кнопка: отклик на касание', () => {
  it('нажатие слегка сжимает кнопку — на телефоне наведения нет, это единственный отклик', () => {
    render(<Button>Купить</Button>);
    const button = screen.getByRole('button', { name: 'Купить' });
    expect(button).toHaveClass('active:scale-[0.98]');
    expect(button).toHaveClass('motion-reduce:transition-none');
  });

  it('неактивная и в загрузке — не сжимается', () => {
    render(
      <>
        <Button disabled>Нет</Button>
        <Button isLoading>Ждём</Button>
      </>,
    );
    for (const name of ['Нет', 'Ждём'])
      expect(screen.getByRole('button', { name })).not.toHaveClass('active:scale-[0.98]');
  });
});
