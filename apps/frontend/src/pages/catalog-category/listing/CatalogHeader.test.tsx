import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { describe, expect, it } from 'vitest';
import type { Category } from '../../../lib/api';
import CatalogHeader from './CatalogHeader';

const parent = { id: 1, name: 'Мягкая мебель', slug: 'myagkaya', full_path: '/catalog/myagkaya' };
const category = { id: 2, name: 'Диваны прямые', slug: 'pryamye', parent } as unknown as Category;

describe('верх списка', () => {
  it('крошки с родителем, заголовок и число товаров', () => {
    render(
      <MemoryRouter>
        <CatalogHeader category={category} title="Диваны прямые" total={226} />
      </MemoryRouter>,
    );
    expect(screen.getByRole('link', { name: 'Мягкая мебель' })).toHaveAttribute(
      'href',
      '/catalog/myagkaya',
    );
    expect(screen.getByRole('heading', { level: 1, name: 'Диваны прямые' })).toBeInTheDocument();
    expect(screen.getByText('226 товаров')).toBeInTheDocument();
  });

  it('ниже 550 середина пути свёрнута в «…» — ссылку на ближайшего родителя', () => {
    render(
      <MemoryRouter>
        <CatalogHeader category={category} title="Диваны прямые" total={226} />
      </MemoryRouter>,
    );
    expect(screen.getByRole('link', { name: 'Назад: Мягкая мебель' })).toHaveAttribute(
      'href',
      '/catalog/myagkaya',
    );
  });

  it('пока число неизвестно — без него', () => {
    render(
      <MemoryRouter>
        <CatalogHeader category={null} title="Каталог" total={null} />
      </MemoryRouter>,
    );
    expect(screen.queryByText(/товар/)).not.toBeInTheDocument();
  });
});
