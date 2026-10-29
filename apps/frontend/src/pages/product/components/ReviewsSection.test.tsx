import { fireEvent, render, screen, within } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import type { Review } from '../../../lib/api';
import ReviewsSection from './ReviewsSection';

const review = (i: number): Review => ({
  id: i,
  name: `Покупатель ${i}`,
  rating: i % 2 ? 5 : 4,
  comment: `Текст отзыва ${i}`,
  created_at: '28 September 2026',
});
const reviews = Array.from({ length: 12 }, (_, i) => review(i + 1));

const setup = (props: Partial<Parameters<typeof ReviewsSection>[0]> = {}) => {
  const onWriteReview = vi.fn();
  render(
    <ReviewsSection
      rating={4.6}
      count={12}
      reviews={reviews}
      status="ready"
      onWriteReview={onWriteReview}
      onRetry={() => {}}
      {...props}
    />,
  );
  return { onWriteReview };
};

describe('отзывы', () => {
  it('сводка: оценка, звёзды, число, «Написать отзыв» и подпись', () => {
    const { onWriteReview } = setup();
    const summary = screen.getByRole('region', { name: 'Оценка товара' });
    expect(within(summary).getByText('4,6')).toBeInTheDocument();
    expect(within(summary).getByRole('img', { name: 'Оценка 4,6 из 5' })).toBeInTheDocument();
    expect(within(summary).getByText('12 отзывов')).toBeInTheDocument();
    expect(within(summary).getByText('Отзыв появится на сайте после проверки')).toBeInTheDocument();
    fireEvent.click(within(summary).getByRole('button', { name: 'Написать отзыв' }));
    expect(onWriteReview).toHaveBeenCalledOnce();
  });

  it('карточка: буква, имя, дата по-русски, звёзды, текст', () => {
    setup();
    const card = screen.getAllByRole('article')[0];
    expect(within(card).getByText('П')).toBeInTheDocument();
    expect(within(card).getByText('Покупатель 1')).toBeInTheDocument();
    expect(within(card).getByText('28 сентября 2026')).toBeInTheDocument();
    expect(within(card).getByRole('img', { name: 'Оценка 5 из 5' })).toBeInTheDocument();
    expect(within(card).getByText('Текст отзыва 1')).toBeInTheDocument();
  });

  it('по 5: «Показать ещё 5 отзывов», потом остаток, потом кнопки нет', () => {
    setup();
    expect(screen.getAllByRole('article')).toHaveLength(5);
    fireEvent.click(screen.getByRole('button', { name: 'Показать ещё 5 отзывов' }));
    expect(screen.getAllByRole('article')).toHaveLength(10);
    fireEvent.click(screen.getByRole('button', { name: 'Показать ещё 2 отзыва' }));
    expect(screen.getAllByRole('article')).toHaveLength(12);
    expect(screen.queryByRole('button', { name: /Показать ещё/ })).toBeNull();
  });

  it('пока грузятся — сводка и заготовки карточек', () => {
    setup({ reviews: [], status: 'loading' });
    expect(screen.getByRole('region', { name: 'Оценка товара' })).toBeInTheDocument();
    expect(screen.queryAllByRole('article')).toHaveLength(0);
    expect(screen.getByLabelText('Отзывы загружаются')).toBeInTheDocument();
  });

  it('ошибка — сообщение и «Повторить»', () => {
    const onRetry = vi.fn();
    setup({ reviews: [], status: 'error', onRetry });
    expect(screen.getByText('Не удалось загрузить отзывы')).toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: 'Повторить' }));
    expect(onRetry).toHaveBeenCalledOnce();
  });
});
