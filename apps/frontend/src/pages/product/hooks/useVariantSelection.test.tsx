import type { ReactNode } from 'react';
import { act, renderHook } from '@testing-library/react';
import { renderToString } from 'react-dom/server';
import { MemoryRouter, StaticRouter, useLocation, useNavigationType } from 'react-router-dom';
import { describe, expect, it } from 'vitest';
import type { ProductDetail, ProductVariant } from '../../../lib/api';
import { useVariantSelection } from './useVariantSelection';

const variant = (id: number, color: string, inStock: boolean) =>
  ({
    id,
    sku: `SKU-${id}`,
    slug: `divan-${color}`,
    price: 1000 * id,
    in_stock: inStock,
    variation_attributes: [{ attribute_slug: 'color', value_slug: color, value_name: color }],
  }) as unknown as ProductVariant;

const product = {
  id: 1,
  slug: 'divan',
  name: 'Диван',
  is_variable: true,
  is_variant: false,
  variants: [variant(10, 'grey', false), variant(11, 'beige', true), variant(12, 'blue', true)],
  variation_attributes: [
    {
      attribute_slug: 'color',
      attribute_name: 'Цвет',
      type: 'color',
      values: [
        { slug: 'grey', name: 'Серый', code: '#808080' },
        { slug: 'beige', name: 'Бежевый', code: '#F5F5DC' },
        { slug: 'blue', name: 'Синий', code: '#000080' },
      ],
    },
  ],
} as unknown as ProductDetail;

/**
 * Само предложение: каталог теперь ведёт на его страницу, а не на родителя
 * (market-docs/61). `variants` приходят те же, среди них оно само.
 */
const offer = {
  ...product,
  id: 11,
  slug: 'divan-beige',
  is_variable: false,
  is_variant: true,
} as unknown as ProductDetail;

const renderAt = (url: string, item: ProductDetail = product) =>
  renderHook(
    () => ({
      selection: useVariantSelection(item),
      location: useLocation(),
      navigation: useNavigationType(),
    }),
    {
      wrapper: ({ children }: { children: ReactNode }) => (
        <MemoryRouter initialEntries={[url]}>{children}</MemoryRouter>
      ),
    },
  );

describe('выбор варианта на странице товара', () => {
  it('без параметра — первый в наличии, адрес не трогаем', () => {
    const { result } = renderAt('/product/divan');
    expect(result.current.selection.selectedVariant?.id).toBe(11);
    expect(result.current.location.search).toBe('');
  });

  it('по ссылке с ?variant — этот вариант, даже без остатка', () => {
    const { result } = renderAt('/product/divan?variant=10');
    expect(result.current.selection.selectedVariant?.id).toBe(10);
  });

  it('неизвестный variant — как без параметра', () => {
    const { result } = renderAt('/product/divan?variant=999');
    expect(result.current.selection.selectedVariant?.id).toBe(11);
  });

  it('выбор значения пишет вариант в адрес заменой, без нового шага истории', () => {
    const { result } = renderAt('/product/divan?utm=x');
    act(() => result.current.selection.selectValue('color', 'blue'));
    expect(result.current.selection.selectedVariant?.id).toBe(12);
    expect(result.current.location.search).toBe('?utm=x&variant=12');
    expect(result.current.navigation).toBe('REPLACE');
  });

  it('значения атрибутов: выбранное и подпись', () => {
    const { result } = renderAt('/product/divan?variant=12');
    const [color] = result.current.selection.attributes;
    expect(color.name).toBe('Цвет');
    expect(color.selectedName).toBe('Синий');
    expect(color.values.map((v) => [v.slug, v.selected, v.available])).toEqual([
      ['grey', false, true],
      ['beige', false, true],
      ['blue', true, true],
    ]);
  });

  it('открыто само предложение — выбор виден, выбрано оно само', () => {
    const { result } = renderAt('/product/divan-beige', offer);

    expect(result.current.selection.selectedVariant?.id).toBe(11);
    const [color] = result.current.selection.attributes;
    expect(color.selectedName).toBe('Бежевый');
    expect(color.values.map((v) => v.slug)).toEqual(['grey', 'beige', 'blue']);
  });

  it('у предложения выбор уводит на страницу соседнего, а не в ?variant', () => {
    const { result } = renderAt('/product/divan-beige', offer);
    act(() => result.current.selection.selectValue('color', 'blue'));

    expect(result.current.location.pathname).toBe('/product/divan-blue');
    expect(result.current.location.search).toBe('');
    // «Назад» не должен перебирать клики по цветам (57, история 25)
    expect(result.current.navigation).toBe('REPLACE');
  });

  it('сервер по ?variant отдаёт выбранный вариант в HTML', () => {
    function Probe() {
      return <>{useVariantSelection(product).selectedVariant?.sku}</>;
    }
    const html = renderToString(
      <StaticRouter location="/product/divan?variant=12">
        <Probe />
      </StaticRouter>,
    );
    expect(html).toBe('SKU-12');
  });
});
