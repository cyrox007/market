import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import ProductEmptyState, { ReviewsEmpty } from './ProductEmptyState';

describe('заглушка пустой вкладки', () => {
  it('заголовок и текст, без кнопки по умолчанию', () => {
    render(<ProductEmptyState icon="description" title="Описания пока нет" text="Скоро добавим" />);
    expect(screen.getByRole('heading', { name: 'Описания пока нет' })).toBeInTheDocument();
    expect(screen.getByText('Скоро добавим')).toBeInTheDocument();
    expect(screen.queryByRole('button')).toBeNull();
  });

  it('отзывов нет — «Написать отзыв» вызывает обработчик', () => {
    const onWrite = vi.fn();
    render(<ReviewsEmpty onWriteReview={onWrite} />);
    expect(screen.getByRole('heading', { name: 'Отзывов пока нет' })).toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: 'Написать отзыв' }));
    expect(onWrite).toHaveBeenCalledOnce();
  });
});
