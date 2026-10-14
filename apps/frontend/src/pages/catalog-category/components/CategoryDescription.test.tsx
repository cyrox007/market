import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import CategoryDescription from './CategoryDescription';

describe('SEO-блок раздела', () => {
  it('заголовок — первая строка описания из админки, остальное — текст (market-docs/44, ответ 8)', () => {
    render(
      <CategoryDescription
        name="Прямые диваны"
        description={'Прямые диваны в Липецке и ещё 15 городах\nВ разделе 226 прямых диванов.'}
      />,
    );
    expect(
      screen.getByRole('heading', { name: 'Прямые диваны в Липецке и ещё 15 городах' }),
    ).toBeInTheDocument();
    expect(screen.getByText('В разделе 226 прямых диванов.')).toBeInTheDocument();
  });

  it('описание в одну строку — заголовком название раздела', () => {
    render(<CategoryDescription name="Прямые диваны" description="Классические прямые диваны." />);
    expect(screen.getByRole('heading', { name: 'Прямые диваны' })).toBeInTheDocument();
    expect(screen.getByText('Классические прямые диваны.')).toBeInTheDocument();
  });
});
