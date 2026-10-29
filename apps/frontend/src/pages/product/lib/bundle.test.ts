import { describe, expect, it } from 'vitest';
import type { ProductDetail, ProductVariant } from '../../../lib/api';
import { bundleCheckout, bundleParts } from './bundle';
import { isSellableOffer } from './variants';

const part = (product_id: number, sku: string, total: number) => ({
  product_id,
  sku,
  name: `Часть ${sku}`,
  quantity: 1,
  unit_price: total,
  total,
});

const offer = (id: number, configured = true) =>
  ({
    id,
    sku: `K-${id}`,
    price: configured ? 22730 : 0,
    is_bundle_configured: configured,
    bundle_components: configured
      ? [part(id * 10, `S-${id}`, 16780), part(id * 10 + 1, `A-${id}`, 5950)]
      : [],
  }) as unknown as ProductVariant;

const product = (fields: Partial<ProductDetail>) =>
  ({ id: 1, name: 'Шкаф', price: 10000, ...fields }) as ProductDetail;

describe('предложение комплекта', () => {
  it('без настроенного состава не продаётся', () => {
    expect(isSellableOffer(offer(2, false), true)).toBe(false);
  });

  it('с составом и обычный вариант — продаются', () => {
    expect(isSellableOffer(offer(2), true)).toBe(true);
    expect(isSellableOffer({ id: 3 } as ProductVariant, true)).toBe(true);
  });

  it('у обычного товара флаг не учитывается: бэк ставит false всем, кто не комплект', () => {
    expect(isSellableOffer(offer(2, false), false)).toBe(true);
  });
});

describe('оформление комплекта', () => {
  it('обычный товар — не комплект', () => {
    expect(bundleCheckout(product({}), null)).toBeNull();
  });

  it('страница предложения: корень — родитель, основной SKU — предложение', () => {
    const kit = product({
      id: 25813,
      is_variant: true,
      parent_id: 25817,
      is_sellable_bundle: true,
    });
    expect(bundleCheckout(kit, offer(25814))).toEqual({ bundleId: 25817, primarySkuId: 25814 });
  });

  it('страница корня вариативного комплекта: корень — сам товар', () => {
    const kit = product({ id: 25817, is_variable: true, is_sellable_bundle: true });
    expect(bundleCheckout(kit, offer(25813))).toEqual({ bundleId: 25817, primarySkuId: 25813 });
  });

  it('у вариативного корня без выбранного предложения оформлять нечего', () => {
    const kit = product({ id: 25817, is_variable: true, is_sellable_bundle: true });
    expect(bundleCheckout(kit, null)).toBeNull();
  });

  it('простой комплект: основной SKU — сам товар', () => {
    const kit = product({ id: 7, is_sellable_bundle: true });
    expect(bundleCheckout(kit, null)).toEqual({ bundleId: 7, primarySkuId: 7 });
  });
});

describe('состав комплекта', () => {
  it('у обычного товара — пусто', () => {
    expect(bundleParts(product({}), null)).toEqual([]);
  });

  it('вариативный: части выбранного предложения с ценами', () => {
    const kit = product({
      id: 25813,
      is_variant: true,
      parent_id: 25817,
      is_sellable_bundle: true,
    });
    expect(bundleParts(kit, offer(25814))).toEqual([
      {
        key: '258140',
        productId: 258140,
        name: 'Часть S-25814',
        sku: 'S-25814',
        quantity: 1,
        total: 16780,
      },
      {
        key: '258141',
        productId: 258141,
        name: 'Часть A-25814',
        sku: 'A-25814',
        quantity: 1,
        total: 5950,
      },
    ]);
  });

  it('вариативный без состава у предложения берёт состав из товара', () => {
    const kit = product({
      id: 25813,
      is_variant: true,
      parent_id: 25817,
      is_sellable_bundle: true,
      sellable_bundle_components: [part(5, 'X', 100)],
    });
    expect(bundleParts(kit, { id: 25813 } as ProductVariant)).toEqual([
      { key: '5', productId: 5, name: 'Часть X', sku: 'X', quantity: 1, total: 100 },
    ]);
  });

  it('простой: основной товар первым, части без цен', () => {
    const kit = product({
      id: 7,
      sku: 'M-7',
      name: 'Кровать',
      is_sellable_bundle: true,
      sellable_bundle_components: [{ id: 8, sku: 'P-8', name: 'Матрас', quantity: 2 }],
    });
    expect(bundleParts(kit, null)).toEqual([
      { key: '7', productId: 7, name: 'Кровать', sku: 'M-7', quantity: 1, total: null },
      { key: '8', productId: 8, name: 'Матрас', sku: 'P-8', quantity: 2, total: null },
    ]);
  });
});
