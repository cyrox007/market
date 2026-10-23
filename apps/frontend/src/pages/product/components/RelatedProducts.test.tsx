import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import type { Product } from '../../../lib/api';
import RelatedProducts from './RelatedProducts';

vi.mock('../../../components/ui/ProductCardConnected', () => ({
  default: ({ product }: { product: Product }) => <article>{product.name}</article>,
}));

const products = Array.from(
  { length: 6 },
  (_, i) => ({ id: i + 1, name: `Диван ${i + 1}` }) as Product,
);

describe('«Похожие товары»', () => {
  it('заголовок и все карточки в карусели', () => {
    render(<RelatedProducts products={products} />);
    expect(screen.getByRole('heading', { name: 'Похожие товары' })).toBeInTheDocument();
    expect(screen.getAllByRole('article')).toHaveLength(6);
    expect(screen.getByRole('region', { name: 'Похожие товары' })).toBeInTheDocument();
  });

  it('стрелки «назад» и «вперёд»; в начале «назад» неактивна', () => {
    render(<RelatedProducts products={products} />);
    expect(screen.getByRole('button', { name: 'Предыдущие товары' })).toHaveClass('opacity-0');
    expect(screen.getByRole('button', { name: 'Следующие товары' })).toBeInTheDocument();
  });

  it('пусто — блока нет', () => {
    const { container } = render(<RelatedProducts products={[]} />);
    expect(container).toBeEmptyDOMElement();
  });
});
